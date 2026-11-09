(() => {
  const header = document.querySelector('header');
  if (!header) return;
  const updateHeader = () => header.classList.toggle('is-scrolled', window.scrollY > 24);
  updateHeader();
  window.addEventListener('scroll', updateHeader, { passive: true });
  window.addEventListener('pageshow', updateHeader);

  const nav = header.querySelector('nav');
  const isAccountPage = nav && nav.querySelector('a[href="dashboard.php"], a[href="admin.php"]');
  const isMemberPage = nav && nav.querySelector('a[href="dashboard.php"]');
  const addStylesheet = (href) => {
    if (document.querySelector('link[href="' + href + '"]')) return;
    const theme = document.createElement('link');
    theme.rel = 'stylesheet';
    theme.href = href;
    document.head.appendChild(theme);
  };
  if (isAccountPage) {
    document.body.classList.add('account-glass');
    addStylesheet('assets/account-glass.css');
  }
  const path = window.location.pathname.split('/').pop();
  if (path === 'login.php') addStylesheet('assets/login-glass.css');
  addStylesheet('assets/footer-credit.css');
  const footer = document.querySelector('footer');
  if (footer && !footer.querySelector('.developer-credit')) {
    const credit = document.createElement('span');
    credit.className = 'developer-credit';
    credit.textContent = 'Developer ARCHIE';
    footer.appendChild(credit);
  }

  if (isMemberPage) {
    addStylesheet('assets/member-notifications.css');
    if (!nav.querySelector('.opportunities-link')) {
      const opportunities = document.createElement('a');
      opportunities.className = 'opportunities-link';
      opportunities.href = 'https://www.michongodaily.site';
      opportunities.textContent = 'Opportunities';
      opportunities.target = '_blank';
      opportunities.rel = 'noopener noreferrer';
      nav.insertBefore(opportunities, nav.querySelector('a[href="profile.php"]') || nav.querySelector('form'));
    }
    const messagesLink = nav.querySelector('a[href="messages.php"]') || document.createElement('a');
    messagesLink.href = 'messages.php';
    messagesLink.textContent = 'Messages';
    messagesLink.classList.add('messages-nav-link');
    if (!messagesLink.parentNode) {
      const connectionsLink = nav.querySelector('a[href="requests.php"]');
      nav.insertBefore(messagesLink, connectionsLink ? connectionsLink.nextSibling : (nav.querySelector('form') || null));
    }

    fetch('member-counts.php', { credentials: 'same-origin', cache: 'no-store' })
      .then(response => response.ok ? response.json() : null)
      .then(counts => {
        if (!counts) return;
        const unread = Number(counts.unread_messages) || 0;
        const pending = Number(counts.pending_requests) || 0;
        if (unread > 0) {
          const badge = document.createElement('span');
          badge.className = 'badge';
          badge.textContent = unread > 99 ? '99+' : String(unread);
          badge.setAttribute('aria-label', unread + ' unread messages');
          messagesLink.append(' ', badge);
        }
        const noticeKey = 'km-connection-request-reminder';
        if (pending > 0 && !sessionStorage.getItem(noticeKey)) {
          sessionStorage.setItem(noticeKey, 'shown');
          const backdrop = document.createElement('div');
          backdrop.className = 'message-reminder-backdrop';
          backdrop.setAttribute('role', 'presentation');
          backdrop.innerHTML = '<section class="message-reminder" role="alertdialog" aria-modal="true" aria-labelledby="connection-reminder-title"><h2 id="connection-reminder-title">You have connection request' + (pending === 1 ? '' : 's') + '</h2><p>You have ' + pending + ' pending request' + (pending === 1 ? '' : 's') + '. Review and accept requests to connect with other members.</p><div class="message-reminder-actions"><a class="button" href="requests.php">View requests</a><button type="button" class="secondary" data-dismiss-reminder>Later</button></div></section>';
          document.body.appendChild(backdrop);
          backdrop.querySelector('[data-dismiss-reminder]').addEventListener('click', () => backdrop.remove());
          backdrop.addEventListener('click', event => { if (event.target === backdrop) backdrop.remove(); });
        }
      })
      .catch(() => {});

    const logoutForm = nav.querySelector('form[action*="logout"]');
    if (logoutForm) logoutForm.addEventListener('submit', () => sessionStorage.removeItem('km-connection-request-reminder'));
  }

  const paths = {
    'dashboard.php': '<path d="M3 3h7v7H3zM14 3h7v7h-7zM3 14h7v7H3zM14 14h7v7h-7z"/>',
    'admin.php': '<path d="M12 3 3 7v5c0 5 9 9 9 9s9-4 9-9V7l-9-4Z"/><path d="m8 12 3 3 5-6"/>',
    'discover.php': '<path d="M20 10c0 6-8 11-8 11S4 16 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/>',
    'requests.php': '<circle cx="9" cy="8" r="3"/><path d="M3 21v-2a6 6 0 0 1 12 0v2M16 5a3 3 0 0 1 0 6M21 21v-2a6 6 0 0 0-3-5.2"/>',
    'profile.php': '<circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/>',
    'login.php': '<path d="M15 3h5v18h-5M3 12h12m-4-4 4 4-4 4"/>',
    'register.php': '<circle cx="9" cy="8" r="4"/><path d="M2 21v-2a7 7 0 0 1 14 0v2M20 7v6m-3-3h6"/>',
    logout: '<path d="M9 3H4v18h5M9 12h12m-4-4 4 4-4 4"/>'
  };
  header.querySelectorAll('nav a, nav .linkbtn').forEach(control => {
    const key = control.tagName === 'BUTTON' ? 'logout' : (control.getAttribute('href') || '').split('?')[0];
    if (!paths[key]) return;
    const icon = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    icon.setAttribute('viewBox', '0 0 24 24');
    icon.setAttribute('fill', 'none');
    icon.setAttribute('stroke', 'currentColor');
    icon.setAttribute('stroke-width', '1.8');
    icon.setAttribute('stroke-linecap', 'round');
    icon.setAttribute('stroke-linejoin', 'round');
    icon.setAttribute('aria-hidden', 'true');
    icon.setAttribute('focusable', 'false');
    icon.classList.add('km-nav-icon');
    icon.innerHTML = paths[key];
    control.prepend(icon);
  });
})();
