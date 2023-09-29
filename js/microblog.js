'use strict';

import { PK } from './passkeys.js';

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
const pk = new PK({
	'urls': {
		'home': mb.url.origin,
		'create': mb.url.origin+'/pk/create',
		'store': mb.url.origin+'/pk/store',
		'login': mb.url.origin+'/pk/login',
		'verify': mb.url.origin+'/pk/verify',
		'revoke': mb.url.origin+'/pk/revoke'
	},
	'dom': {
		'create': '#passkey-create',
		'revoke': '#passkey-revoke',
		'login': '#passkey-login',
		'status': '#passkey-status'
	},
});
