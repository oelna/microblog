class THEME {
	constructor (params) {
		this.params = params;
		console.log('Init theme class', this.params);
	}

	demo () {
		console.log('Hello!');
	}
}

// run custom theme code
// const thm = new THEME('abc');
console.log('Init theme script');

export { THEME }
