'use strict';

document.addEventListener('DOMContentLoaded', () => {
  const topButton = document.getElementById('go-top');
  const updateTop = () => {
    if (topButton) topButton.style.display = window.scrollY > 250 ? 'block' : 'none';
  };
  window.addEventListener('scroll', updateTop, { passive: true });
  updateTop();
  topButton?.addEventListener('click', () => window.scrollTo({ top: 0, behavior: 'smooth' }));

  document.querySelectorAll('textarea.autogrow').forEach((area) => {
    const resize = () => {
      area.style.height = 'auto';
      area.style.height = `${Math.max(area.scrollHeight, 40)}px`;
    };
    area.addEventListener('input', resize);
    resize();
  });

  document.querySelectorAll('[data-confirm]').forEach((button) => {
    button.addEventListener('click', (event) => {
      if (!window.confirm(button.dataset.confirm || 'Continue?')) event.preventDefault();
    });
  });

  document.querySelectorAll('[data-add-child]').forEach((button) => {
    button.addEventListener('click', () => {
      const target = document.getElementById(button.dataset.addChild);
      const template = target?.querySelector('template');
      if (!target || !template) return;
      const row = template.content.cloneNode(true);
      const index = Number(target.dataset.nextIndex || '0');
      row.querySelectorAll('[data-row-index]').forEach((el) => {
        el.name = el.name.replace('__INDEX__', String(index));
      });
      target.dataset.nextIndex = String(index + 1);
      target.insertBefore(row, template);
    });
  });
});
