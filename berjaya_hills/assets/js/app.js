document.addEventListener('DOMContentLoaded',()=>{document.querySelectorAll('.mobile-menu').forEach(b=>b.addEventListener('click',()=>document.querySelector('.main-nav')?.classList.toggle('open')));document.querySelectorAll('[data-tabs] button').forEach(b=>b.addEventListener('click',()=>{const box=b.closest('[data-tabs]');box.querySelectorAll('button').forEach(x=>x.classList.remove('active'));box.querySelectorAll('[data-tab]').forEach(x=>x.hidden=true);b.classList.add('active');box.querySelector(`[data-tab="${b.dataset.tab}"]`)?.removeAttribute('hidden')}));document.querySelectorAll('[data-modal]').forEach(b=>b.addEventListener('click',()=>document.getElementById(b.dataset.modal)?.classList.add('show')));document.querySelectorAll('.modal-close').forEach(b=>b.addEventListener('click',()=>b.closest('.modal')?.classList.remove('show')));setTimeout(()=>document.querySelectorAll('.toast').forEach(x=>x.classList.add('hide')),4500);});

/* Profile notification preference: the single email preference controls all email columns. */
document.addEventListener('DOMContentLoaded', function () {
  const primary = document.querySelector('input[name="email_enabled"]');
  if (!primary) return;
  document.querySelectorAll('.email-proxy').forEach(function (proxy) {
    proxy.addEventListener('change', function () { primary.checked = proxy.checked; });
    proxy.checked = primary.checked;
  });
});


/* Profile photo: the Change Photo button opens the native file picker, previews the image, then submits the existing secure POST form. */
document.addEventListener('DOMContentLoaded', function () {
  const photoButton = document.getElementById('change-photo-btn');
  const photoInput = document.getElementById('profile-photo-input');
  const photoForm = document.getElementById('profile-photo-form');
  const avatar = document.getElementById('profile-avatar-preview');
  if (!photoButton || !photoInput || !photoForm) return;

  photoButton.addEventListener('click', function () {
    photoInput.click();
  });

  photoInput.addEventListener('change', function () {
    const file = photoInput.files && photoInput.files[0];
    if (!file) return;

    const allowed = ['image/jpeg', 'image/png', 'image/webp'];
    if (!allowed.includes(file.type)) {
      alert('Please choose a JPG, PNG or WEBP image.');
      photoInput.value = '';
      return;
    }
    if (file.size > 2 * 1024 * 1024) {
      alert('Profile photo must be 2MB or smaller.');
      photoInput.value = '';
      return;
    }

    if (avatar && avatar.tagName === 'IMG') {
      avatar.src = URL.createObjectURL(file);
    } else if (avatar) {
      const img = document.createElement('img');
      img.id = 'profile-avatar-preview';
      img.className = 'profile-avatar profile-avatar-image';
      img.alt = 'Profile photo';
      img.src = URL.createObjectURL(file);
      avatar.replaceWith(img);
    }

    photoButton.disabled = true;
    photoButton.textContent = 'Uploading…';
    photoForm.submit();
  });
});


/* User sidebar: collapsible navigation, with a compact icon-only state. */
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.user-shell .sidebar-collapse-toggle').forEach(function (button) {
    button.addEventListener('click', function () {
      const shell = button.closest('.user-shell');
      if (!shell) return;
      const collapsed = shell.classList.toggle('user-sidebar-collapsed');
      button.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
      button.setAttribute('aria-label', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
      button.title = collapsed ? 'Expand sidebar' : 'Collapse sidebar';
      button.textContent = collapsed ? '›' : '‹';
    });
  });
});
