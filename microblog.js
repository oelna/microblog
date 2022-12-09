'use strict';

const textarea = document.querySelector('textarea[name="content"]');
const charCount = document.querySelector('#count');
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
