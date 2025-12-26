export default class DisclosureButton {
  constructor(buttonNode) {
    this.buttonNode = buttonNode;
    this.controlledNode = false;

    const id = this.buttonNode.getAttribute('aria-controls');

    if (id) {
      this.controlledNode = document.getElementById(id);
    }

    this.buttonNode.addEventListener('click', this.onClick.bind(this));
    this.buttonNode.removeAttribute('hidden')
    this.hideContent()
  }

  showContent() {
    if (this.controlledNode) {
      this.controlledNode.removeAttribute('hidden');
    }

    if (this.buttonNode.hasAttribute('data-collapse-text')) {
      this.buttonNode.setAttribute('data-expand-text', this.buttonNode.textContent)
      this.buttonNode.textContent = this.buttonNode.getAttribute('data-collapse-text')
    }
  }

  hideContent() {
    if (this.controlledNode) {
      this.controlledNode.setAttribute('hidden', '');
    }

    if (this.buttonNode.hasAttribute('data-expand-text')) {
      this.buttonNode.setAttribute('data-collapse-text', this.buttonNode.textContent)
      this.buttonNode.textContent = this.buttonNode.getAttribute('data-expand-text')
    }
  }

  toggleExpand() {
    if (this.buttonNode.getAttribute('aria-expanded') === 'true') {
      this.buttonNode.setAttribute('aria-expanded', 'false');
      this.hideContent();
    } else {
      this.buttonNode.setAttribute('aria-expanded', 'true');
      this.showContent();
    }
  }

  onClick() {
    this.toggleExpand();
  }
}
