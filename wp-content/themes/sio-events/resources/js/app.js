import "./components/burger-menu.js";
import DisclosureButton from "./components/disclosure-button.js";

import.meta.glob([
    '../images/**',
    '../fonts/**',
]);

// Import app.css and all CSS files from blocks, compositions, and utilities folders
// Using import.meta.glob as a workaround for Vite's lack of postcss-import-ext-glob support
import.meta.glob([
    '../css/app.css',
    '../css/blocks/*.css',
    '../css/compositions/*.css',
    '../css/utilities/*.css',
], {
    eager: true,
})

// Initialize disclosure buttons
const disclosureButtons = document.querySelectorAll('button[aria-expanded][aria-controls]');
if (disclosureButtons.length > 0) {
    disclosureButtons.forEach(button => {
        new DisclosureButton(button)
    })
}
