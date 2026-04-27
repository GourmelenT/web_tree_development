document.addEventListener('DOMContentLoaded', () => {
  document.querySelectorAll('.site-nav-shell').forEach((shell) => {
    const toggle = shell.querySelector('.site-nav-toggle');
    const panel = shell.querySelector('.site-nav-panel');

    if (!toggle || !panel) {
      return;
    }

    const syncToggleState = () => {
      toggle.setAttribute('aria-expanded', String(shell.classList.contains('site-nav-open')));
    };

    toggle.addEventListener('click', () => {
      shell.classList.toggle('site-nav-open');
      syncToggleState();
    });

    panel.querySelectorAll('a').forEach((link) => {
      link.addEventListener('click', () => {
        shell.classList.remove('site-nav-open');
        syncToggleState();
      });
    });

    syncToggleState();
  });

  window.addEventListener('resize', () => {
    if (window.innerWidth < 768) {
      return;
    }

    document.querySelectorAll('.site-nav-shell.site-nav-open').forEach((shell) => {
      shell.classList.remove('site-nav-open');
      const toggle = shell.querySelector('.site-nav-toggle');
      if (toggle) {
        toggle.setAttribute('aria-expanded', 'false');
      }
    });
  });
});
