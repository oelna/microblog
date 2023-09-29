// PASSKEY MODULE FOR MICROBLOG

// a lot of the following code has been taken from
// https://github.com/craigfrancis/webauthn-tidy (BSD 3)
// Copyright 2020 Craig Francis
// with modifications by Arno Richter in 2023
// for his Microblog software

class PK {
	constructor (params) {
		this.urls = params.urls;
		this.dom = {};
		for (const [key, value] of Object.entries(params.dom)) {
			this.dom[key] = document.querySelector(value);
		}

		this.textEncoder = new TextEncoder();
		this.textDecoder = new TextDecoder('utf-8');

		this.support = null;
		setTimeout(this.init, 10, this);
	}

	async init (self) {
		self.support = await self.detect();

		if(self.dom.create) {
			self.dom.create.addEventListener('click', async function (e) {
				e.preventDefault();
				await self.create();
			});
		}

		if(self.dom.revoke) {
			self.dom.revoke.addEventListener('click', async function (e) {
				e.preventDefault();
				await self.revoke();
			});
		}

		if(self.dom.login) {
			self.dom.login.addEventListener('click', async function (e) {
				e.preventDefault();
				await self.login();
			});
		}

		console.log('Initialized Passkey UI');
	}

	async detect () {
		const results = await Promise.all([
			PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable(),
			PublicKeyCredential.isConditionalMediationAvailable()
		]);

		if (results.every(r => r === true)) {
			console.log('Passkey support available');
			if(this.dom.create) this.dom.create.classList.remove('hidden');
			if(this.dom.login) this.dom.login.classList.remove('hidden');

			document.documentElement.classList.add('passkeys');

			return true;
		} else document.documentElement.classList.add('no-passkeys');

		return false;
	}

	async create (event) {
		if (!this.support) return false;

		const optionsRequest = await fetch(this.urls.create, {
			'method': 'GET',
			'headers': {
				'Accept': 'application/json',
				'Content-Type': 'application/json'
			}
		});
		const options = await optionsRequest.json();

		options['publicKey']['challenge'] = this.base64ToUint8array(options['publicKey']['challenge']);
		options['publicKey']['user']['id'] = this.textToUint8array(options['publicKey']['user']['id']);

		if (options['publicKey']['excludeCredentials'].length > 0) {
			for (var k = (options['publicKey']['excludeCredentials'].length - 1); k >= 0; k--) {
				options['publicKey']['excludeCredentials'][k]['id'] = this.base64ToUint8array(options['publicKey']['excludeCredentials'][k]['id']);
			}
		}

		let result = null;
		try {
			result = await navigator.credentials.create(options);
			console.log(result);
		} catch (e) {
			if (e.name == 'InvalidStateError') {
				console.error('error', e.name, e.message);
				alert('You already seem to have a passkey on file! You have to revoke it first to set a new one.');
			} else {
				console.error('error', e.name, e.message);
			}
			return false;
		}

		if (!result) return false;

		var output = {
			'id': result.id.replace(/-/g, '+').replace(/_/g, '/'), // Use normal base64, not base64url (rfc4648)
			'type': result.type,
			'response': {
				'clientDataJSON': this.bufferToBase64(result.response.clientDataJSON),
				'authenticatorData': this.bufferToBase64(result.response.getAuthenticatorData()),
				'publicKey': this.bufferToBase64(result.response.getPublicKey()),
				'publicKeyAlg': result.response.getPublicKeyAlgorithm()
			}
		};

		const saveRequest = await fetch(this.urls.store, {
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
			this.dom.status.innerText = 'New Passkey was saved!';
		} else {
			console.error('passkey setup failed (passkey already present in DB)', response.result);
		}
	}

	async login (event) {
		if (!this.support) return false;

		const optionsRequest = await fetch(this.urls.login, {
			'method': 'GET',
			'headers': {
				'Accept': 'application/json',
				'Content-Type': 'application/json'
			}
		});
		const options = await optionsRequest.json();
console.log(options);
		options['publicKey']['challenge'] = this.base64ToUint8array(options['publicKey']['challenge']);

		for (var k = (options['publicKey']['allowCredentials'].length - 1); k >= 0; k--) {
			options['publicKey']['allowCredentials'][k]['id'] = this.base64ToUint8array(options['publicKey']['allowCredentials'][k]['id']);
		}

		const result = await navigator.credentials.get(options);
		// if (!result) return false;
console.log(result);
		// Make result JSON friendly.
		var output = {
			'id': result.id.replace(/-/g, '+').replace(/_/g, '/'), // Use normal base64, not base64url (rfc4648)
			'type': result.type,
			'response': {
				'clientDataJSON': this.bufferToBase64(result.response.clientDataJSON),
				'authenticatorData': this.bufferToBase64(result.response.authenticatorData),
				'signature': this.bufferToBase64(result.response.signature)
			}
		};

		// Complete
		const verifyRequest = await fetch(this.urls.verify, {
			'method': 'POST',
			'headers': {
				'Accept': 'application/json',
				'Content-Type': 'application/json'
			},
			'body': JSON.stringify(output)
		});

		const response = await verifyRequest.json();

		if (response && response.result > -1) {
			console.info('passkey verification successful', response.result);
			window.location.href = this.urls.home;
		} else {
			console.error('passkey verification failed', response.result);
		}
	}

	async revoke (event) {
		if (!this.support) return false;

		if (window.confirm("Really remove your passkey?")) {
			const request = await fetch(this.urls.revoke, {
				'method': 'GET',
				'headers': {
					'Accept': 'application/json',
					'Content-Type': 'application/json'
				}
			});
			const response = await request.json();

			if (response.result > -1) {
				console.info('passkey removed from database');
				this.dom.status.innerText = 'Passkey was removed';
			} else {
				console.error('an error occurred while trying to remove the passkey!', response);
			}
		}
	}

	// helpers

	uint8arrayToBase64(array) { // https://stackoverflow.com/a/12713326/6632
		return window.btoa(String.fromCharCode.apply(null, array));
	}

	uint8arrayToHex(array) { // https://stackoverflow.com/a/40031979/6632
		return Array.prototype.map.call(array, function (x) {
				return ('00' + x.toString(16)).slice(-2);
			}).join('');
	}

	uint8arrayToBuffer(array) { // https://stackoverflow.com/a/54646864/6632
		return array.buffer.slice(array.byteOffset, array.byteLength + array.byteOffset)
	}

	bufferToBase64(buffer) {
		return this.uint8arrayToBase64(new Uint8Array(buffer));
	}

	bufferToHex(buffer) {
		return this.uint8arrayToHex(new Uint8Array(buffer));
	}

	base64ToUint8array(base64) { // https://stackoverflow.com/a/21797381/6632
		var binary = window.atob(base64),
			array = new Uint8Array(new ArrayBuffer(binary.length));

		for (var k = (binary.length - 1); k >= 0; k--) {
			array[k] = binary.charCodeAt(k);
		}

		return array;
	}

	textToUint8array(text) {
		if (!this.textEncoder) this.textEncoder = new TextEncoder();
		return this.textEncoder.encode(text);
	}
}

export { PK }
