'use strict';

document.documentElement.classList.remove('no-js');

const textarea = document.querySelector('textarea[name="content"]');
const charCount = document.querySelector('#count');

if (textarea) {
	const maxCount = parseInt(textarea.getAttribute('maxlength'));

	if (textarea.value.length > 0) {
		const textLength = [...textarea.value].length;
		charCount.textContent = maxCount - textLength;
	} else {
		charCount.textContent = maxCount;
	}

	textarea.addEventListener('input', function () {
		const textLength = [...this.value].length;

		charCount.textContent = maxCount - textLength;
	}, false);
}

const postForm = document.querySelector('#post-new-form');
let useDragDrop = (!!window.FileReader && 'draggable' in document.createElement('div'));
// useDragDrop = false; // remove, only for testing!
if (postForm) {
	const droparea = postForm.querySelector('#post-droparea');
	const attachmentsInput = postForm.querySelector('#post-attachments');
	const data = {
		'attachments': []
	};


	if (droparea && attachmentsInput) {
		if (useDragDrop) {
			console.log('init with modern file attachments');

			const list = postForm.querySelector('#post-attachments-list');
			list.addEventListener('click', function (e) {
				e.preventDefault();

				// remove attachment
				if (e.target.nodeName.toLowerCase() == 'li') {
					const filename = e.target.textContent;

					data.attachments = data.attachments.filter(function (ele) {
						return ele.name !== filename;
					});

					e.target.remove();
				}
			});

			droparea.classList.remove('hidden');
			document.querySelector('#post-attachments-label').classList.add('hidden');

			droparea.ondragover = droparea.ondragenter = function (e) {
				e.stopPropagation();
				e.preventDefault();

				e.dataTransfer.dropEffect = 'copy';
				e.target.classList.add('drag');
			};

			droparea.ondragleave = function (e) {
				e.target.classList.remove('drag');
			};

			droparea.onclick = function (e) {
				e.preventDefault();

				// make a virtual file upload
				const input = document.createElement('input');
				input.type = 'file';
				input.setAttribute('multiple', '');
				input.setAttribute('accept', 'image/*'); // only images for now

				input.onchange = e => {
					processSelectedFiles(e.target.files);
				}

				input.click();
			};

			function processSelectedFiles(files) {
				if (!files || files.length < 1) return;

				for (const file of files) {
					const found = data.attachments.find(ele => ele.name === file.name);
					if(found) continue; // skip existing attachments

					data.attachments.push({
						'name': file.name,
						// todo: maybe some better form of dupe detection here?
						'file': file
					});

					const li = document.createElement('li');
					li.textContent = file.name;

					const reader = new FileReader();

					if(file.type.startsWith('image/')) {
						reader.onload = function (e) {
							var dataURL = e.target.result;

							const preview = document.createElement('img');
							preview.classList.add('file-preview');
							preview.setAttribute('src', dataURL);

							li.prepend(preview);
						};
						reader.onerror = function (e) {
							console.log('An error occurred during file input: '+e.target.error.code);
						};

						reader.readAsDataURL(file);
					}

					list.append(li);
				}
			}

			droparea.ondrop = function (e) {
				if (e.dataTransfer) {
					e.preventDefault();
					e.stopPropagation();

					processSelectedFiles(e.dataTransfer.files);
				}

				e.target.classList.remove('drag');
			};

			postForm.addEventListener('submit', async function (e) {
				e.preventDefault();

				const postFormData = new FormData();

				postFormData.append('content', postForm.querySelector('[name="content"]').value);

				for (const attachment of data.attachments) {
					postFormData.append('attachments[]', attachment.file);
				}

				/*
				for (const pair of postFormData.entries()) {
					console.log(`${pair[0]}, ${pair[1]}`);
				}
				*/

				const response = await fetch(postForm.getAttribute('action'), {
					body: postFormData,
					method: 'POST'
				});

				if (response.ok && response.status == 200) {
					const txt = await response.text();
					// console.log('form result', response, txt);
					window.location.href = postForm.dataset.redirect + '?t=' + Date.now();
				} else {
					console.warn('error during post submission!', response);
				}
			});
		} else {
			// use the native file input dialog
			// but enhanced
			if (attachmentsInput) {
				console.log('init with classic file attachments');

				attachmentsInput.addEventListener('change', function (e) {
					console.log(e.target.files);

					const list = postForm.querySelector('#post-attachments-list');
					list.replaceChildren();

					for (const file of e.target.files) {
						const li = document.createElement('li');
						li.textContent = file.name;
						list.append(li);
					}
				});
			}
		}
	}
}

// better rounded corners
if ('paintWorklet' in CSS) {
	// CSS.paintWorklet.addModule('./js/squircle.js');
}


// PASSKEY SUPPORT

// a lot of the following code has been taken from
// https://github.com/craigfrancis/webauthn-tidy (BSD 3)
// Copyright 2020 Craig Francis
// with modifications by Arno Richter in 2023
// for his Microblog software

const PKCreate = document.querySelector('#passkey-create');
const PKRevoke = document.querySelector('#passkey-revoke');
const PKLogin = document.querySelector('#passkey-login');
const textEncoder = new TextEncoder();
const textDecoder = new TextDecoder('utf-8');

function uint8array_to_base64(array) { // https://stackoverflow.com/a/12713326/6632
	return window.btoa(String.fromCharCode.apply(null, array));
}

function uint8array_to_hex(array) { // https://stackoverflow.com/a/40031979/6632
	return Array.prototype.map.call(array, function (x) {
			return ('00' + x.toString(16)).slice(-2);
		}).join('');
}

function uint8array_to_buffer(array) { // https://stackoverflow.com/a/54646864/6632
	return array.buffer.slice(array.byteOffset, array.byteLength + array.byteOffset)
}

function buffer_to_base64(buffer) {
	return uint8array_to_base64(new Uint8Array(buffer));
}

function buffer_to_hex(buffer) {
	return uint8array_to_hex(new Uint8Array(buffer));
}

function base64_to_uint8array(base64) { // https://stackoverflow.com/a/21797381/6632
	var binary = window.atob(base64),
		array = new Uint8Array(new ArrayBuffer(binary.length));

	for (var k = (binary.length - 1); k >= 0; k--) {
		array[k] = binary.charCodeAt(k);
	}

	return array;
}

function text_to_uint8array(text) {
	if (!textEncoder) textEncoder = new TextEncoder();
	return textEncoder.encode(text);
}

if (window.PublicKeyCredential && PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable && PublicKeyCredential.isConditionalMediationAvailable) {
	Promise.all([
		PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable(),
		PublicKeyCredential.isConditionalMediationAvailable(),
	]).then(results => {
		if (results.every(r => r === true)) {
			if(PKCreate) PKCreate.classList.remove('hidden');
			if(PKLogin) PKLogin.classList.remove('hidden');
			
			document.documentElement.classList.add('passkeys');
			mb.passkeys = true;
		} else document.documentElement.classList.add('no-passkeys');
	});
}

if(PKCreate) {
	PKCreate.addEventListener('click', async function (e) {
		e.preventDefault();

		const optionsRequest = await fetch(mb.url.origin+'/pk?q=create', {
			'method': 'GET',
			'headers': {
				'Accept': 'application/json',
				'Content-Type': 'application/json'
			}
		});
		const options = await optionsRequest.json();

		options['publicKey']['challenge'] = base64_to_uint8array(options['publicKey']['challenge']);
		options['publicKey']['user']['id'] = text_to_uint8array(options['publicKey']['user']['id']);

		if (options['publicKey']['excludeCredentials'].length > 0) {
			for (var k = (options['publicKey']['excludeCredentials'].length - 1); k >= 0; k--) {
				options['publicKey']['excludeCredentials'][k]['id'] = base64_to_uint8array(options['publicKey']['excludeCredentials'][k]['id']);
			}
		}

		try {
			const result = await navigator.credentials.create(options);
		} catch (e) {
			if (e.name == 'InvalidStateError') {
				console.error('error', e.name, e.message);
				alert('You already seem to have a passkey on file! You have to revoke it first to set a new one.');
			} else {
				console.error('error', e.name, e.message);
			}
			return false;
		}

		var output = {
			'id':   result.id.replace(/-/g, '+').replace(/_/g, '/'), // Use normal base64, not base64url (rfc4648)
			'type': result.type,
			'response': {
				'clientDataJSON':    buffer_to_base64(result.response.clientDataJSON),
				'authenticatorData': buffer_to_base64(result.response.getAuthenticatorData()),
				'publicKey':         buffer_to_base64(result.response.getPublicKey()),
				'publicKeyAlg':      result.response.getPublicKeyAlgorithm()
			}
		};

		const saveRequest = await fetch(mb.url.origin+'/pk?q=store', {
			'method': 'POST',
			'headers': {
				'Accept': 'application/json',
				'Content-Type': 'application/json'
			},
			'body': JSON.stringify(output)
		});
		const response = await saveRequest.json();

		if (response.result > -1) {
			console.info('passkey setup successful', response.result);
			document.querySelector('#passkey-status').innerText = 'New Passkey was saved!';
		} else {
			console.error('passkey setup failed (passkey already present in DB)', response.result);
		}
	});
}

if(PKRevoke) {
	PKRevoke.addEventListener('click', async function (e) {
		e.preventDefault();

		if (window.confirm("Really remove your passkey?")) {
			const request = await fetch(mb.url.origin+'/pk?q=revoke', {
				'method': 'GET',
				'headers': {
					'Accept': 'application/json',
					'Content-Type': 'application/json'
				}
			});
			const response = await request.json();

			if (response.result > -1) {
				console.info('passkey removed from database');
				document.querySelector('#passkey-status').innerText = 'Passkey was removed';
			} else {
				console.error('an error occurred while trying to remove the passkey!', response);
			}
		}
	});
}

if(PKLogin) {
	PKLogin.addEventListener('click', async function (e) {
		e.preventDefault();

		const optionsRequest = await fetch(mb.url.origin+'/pk?q=login', {
			'method': 'GET',
			'headers': {
				'Accept': 'application/json',
				'Content-Type': 'application/json'
			}
		});
		const options = await optionsRequest.json();
		
		options['publicKey']['challenge'] = base64_to_uint8array(options['publicKey']['challenge']);

		for (var k = (options['publicKey']['allowCredentials'].length - 1); k >= 0; k--) {
			options['publicKey']['allowCredentials'][k]['id'] = base64_to_uint8array(options['publicKey']['allowCredentials'][k]['id']);
		}

		const result = await navigator.credentials.get(options);

		// Make result JSON friendly.
		var output = {
			'id':   result.id.replace(/-/g, '+').replace(/_/g, '/'), // Use normal base64, not base64url (rfc4648)
			'type': result.type,
			'response': {
				'clientDataJSON':    buffer_to_base64(result.response.clientDataJSON),
				'authenticatorData': buffer_to_base64(result.response.authenticatorData),
				'signature':         buffer_to_base64(result.response.signature)
			}
		};

		// Complete
		const verifyRequest = await fetch(mb.url.origin+'/pk?q=verify', {
			'method': 'POST',
			'headers': {
				'Accept': 'application/json',
				'Content-Type': 'application/json'
			},
			'body': JSON.stringify(output)
		});
		
		const response = await verifyRequest.json();

		if (response.result > -1) {
			console.info('passkey verification successful', response.result);
			// alert('Verification success!');
			window.location.href = mb.url;
		} else {
			console.error('passkey verification failed', response.result);
		}
	});
}
