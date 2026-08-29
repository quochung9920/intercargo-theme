const body = document.body;
const toggle = document.querySelector('.menu-toggle');
const drawer = document.querySelector('#mobile-menu');
const closeButton = document.querySelector('.mobile-menu-close');

function setMenu(open) {
  if (!toggle || !drawer) return;
  toggle.setAttribute('aria-expanded', String(open));
  toggle.textContent = open ? 'CLOSE' : 'MENU';
  drawer.setAttribute('aria-hidden', String(!open));
  drawer.dataset.open = String(open);
  body.classList.toggle('menu-open', open);
}

toggle?.addEventListener('click', () => setMenu(toggle.getAttribute('aria-expanded') !== 'true'));
closeButton?.addEventListener('click', () => setMenu(false));
drawer?.addEventListener('click', (event) => {
  if (event.target instanceof HTMLAnchorElement) setMenu(false);
});
document.addEventListener('keydown', (event) => {
  if (event.key === 'Escape' && toggle?.getAttribute('aria-expanded') === 'true') {
    setMenu(false);
    toggle.focus();
  }
});
window.addEventListener('resize', () => {
  if (window.innerWidth >= 1200) setMenu(false);
});

document.querySelectorAll('.enquiry-form').forEach((form) => form.addEventListener('submit', (event) => event.preventDefault()));

document.addEventListener('click', (event) => {
  const button = event.target instanceof Element ? event.target.closest('.faq-question') : null;
  if (!(button instanceof HTMLButtonElement)) return;
  const item = button.closest('.faq-item');
  const answerId = button.getAttribute('aria-controls');
  const answer = answerId ? document.getElementById(answerId) : null;
  const open = button.getAttribute('aria-expanded') !== 'true';
  button.setAttribute('aria-expanded', String(open));
  item?.classList.toggle('is-open', open);
  if (answer) answer.hidden = !open;
  const plus = button.querySelector('.faq-plus');
  if (plus) plus.textContent = open ? '−' : '+';
});
