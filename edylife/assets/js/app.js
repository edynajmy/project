(() => {
  'use strict';

  const toast = document.getElementById('toast');
  // Persist the user's preferred appearance across pages and visits.
  const themeToggle = document.getElementById('themeToggle');
  const rootElement = document.documentElement;
  function applyTheme(theme) {
    rootElement.dataset.theme = theme;
    try { localStorage.setItem('noirlife-theme', theme); } catch (e) {}
    if (themeToggle) {
      const light = theme === 'light';
      themeToggle.setAttribute('aria-label', light ? 'Switch to dark mode' : 'Switch to light mode');
      themeToggle.title = light ? 'Switch to dark mode' : 'Switch to light mode';
      const icon = themeToggle.querySelector('.theme-icon');
      const label = themeToggle.querySelector('.theme-label');
      if (icon) icon.textContent = light ? '☾' : '☼';
      if (label) label.textContent = light ? 'Dark mode' : 'Light mode';
    }
  }
  applyTheme(rootElement.dataset.theme || 'dark');
  themeToggle?.addEventListener('click', () => applyTheme(rootElement.dataset.theme === 'light' ? 'dark' : 'light'));

  let toastTimer;

  function showToast(message) {
    if (!toast) return;
    toast.textContent = message;
    toast.classList.add('show');
    window.clearTimeout(toastTimer);
    toastTimer = window.setTimeout(() => toast.classList.remove('show'), 2600);
  }

  function openModal(id) {
    const modal = document.getElementById(id);
    if (!modal) return;
    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('modal-open');
    const firstInput = modal.querySelector('input:not([type="hidden"]), select, textarea');
    if (firstInput) window.setTimeout(() => firstInput.focus(), 80);
  }

  function closeModal(modal) {
    if (!modal) return;
    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');
    if (!document.querySelector('.modal-backdrop.is-open')) {
      document.body.classList.remove('modal-open');
    }
  }

  document.querySelectorAll('[data-open-modal]').forEach(button => {
    button.addEventListener('click', () => openModal(button.dataset.openModal));
  });

  document.querySelectorAll('[data-close-modal]').forEach(button => {
    button.addEventListener('click', () => closeModal(button.closest('.modal-backdrop')));
  });

  document.querySelectorAll('.modal-backdrop').forEach(modal => {
    modal.addEventListener('click', event => {
      if (event.target === modal) closeModal(modal);
    });
  });

  document.addEventListener('keydown', event => {
    if (event.key === 'Escape') {
      document.querySelectorAll('.modal-backdrop.is-open').forEach(closeModal);
      document.getElementById('sidebar')?.classList.remove('sidebar-open');
    }
  });

  const menuToggle = document.getElementById('menuToggle');
  const sidebar = document.getElementById('sidebar');
  menuToggle?.addEventListener('click', () => sidebar?.classList.toggle('sidebar-open'));

  document.querySelectorAll('[data-coming-soon]').forEach(link => {
    link.addEventListener('click', event => {
      event.preventDefault();
      showToast(`${link.dataset.comingSoon} will be added in the next module.`);
    });
  });

  document.querySelectorAll('.flash-close').forEach(button => {
    button.addEventListener('click', () => button.closest('.flash-message')?.remove());
  });

  document.querySelectorAll('.task-check-form').forEach(form => {
    form.addEventListener('submit', () => {
      const button = form.querySelector('button[type="submit"]');
      if (button) button.classList.add('is-loading');
    });
  });

  document.querySelectorAll('[data-confirm]').forEach(form => form.addEventListener('submit', event => {
    if (!window.confirm(form.dataset.confirm || 'Are you sure?')) event.preventDefault();
  }));
  document.querySelectorAll('[data-edit-task]').forEach(button => button.addEventListener('click', () => {
    const x = JSON.parse(button.dataset.editTask);
    document.getElementById('editTaskId').value=x.id; document.getElementById('editTaskTitle').value=x.title;
    document.getElementById('editTaskCategory').value=x.category; document.getElementById('editTaskPriority').value=x.priority;
    document.getElementById('editTaskDate').value=x.due_date; openModal('editTaskModal');
  }));
  document.querySelectorAll('[data-edit-activity]').forEach(button => button.addEventListener('click', () => {
    const x=JSON.parse(button.dataset.editActivity);
    document.getElementById('editActivityId').value=x.id; document.getElementById('editActivityTitle').value=x.title;
    document.getElementById('editActivityCategory').value=x.category; document.getElementById('editActivityDate').value=x.activity_date;
    document.getElementById('editActivityStart').value=String(x.start_time).slice(0,5);
    document.getElementById('editActivityEnd').value=x.end_time?String(x.end_time).slice(0,5):'';
    document.getElementById('editActivityNotes').value=x.notes||''; openModal('editActivityModal');
  }));
  document.querySelectorAll('[data-edit-folder]').forEach(button => button.addEventListener('click', () => {
    const x=JSON.parse(button.dataset.editFolder);
    document.getElementById('folderEditId').value=x.id; document.getElementById('folderEditName').value=x.name;
    document.getElementById('folderEditDescription').value=x.description||''; openModal('editFolderModal');
  }));
  document.querySelectorAll('[data-edit-media]').forEach(button => button.addEventListener('click', () => {
    const x=JSON.parse(button.dataset.editMedia);
    document.getElementById('mediaEditId').value=x.id; document.getElementById('mediaEditName').value=x.title;
    document.getElementById('mediaEditType').value=x.media_type; document.getElementById('mediaEditYear').value=x.release_year||'';
    document.getElementById('mediaEditStatus').value=x.watch_status; document.getElementById('mediaEditRating').value=x.rating===null?'':x.rating;
    document.getElementById('mediaEditGenre').value=x.genre||''; document.getElementById('mediaEditNotes').value=x.notes||'';
    openModal('mediaEditModal');
  }));
  document.querySelectorAll('[data-edit-photo]').forEach(button => button.addEventListener('click', () => {
    const x=JSON.parse(button.dataset.editPhoto);
    document.getElementById('photoEditId').value=x.id; document.getElementById('photoEditFolder').value=x.folder_id;
    document.getElementById('photoEditCaption').value=x.caption||''; document.getElementById('photoEditDate').value=x.taken_on||'';
    openModal('editPhotoModal');
  }));
  const viewer=document.getElementById('photoViewer');
  function closeViewer(){if(!viewer)return;viewer.classList.remove('is-open');viewer.setAttribute('aria-hidden','true');viewer.querySelector('img').src='';if(!document.querySelector('.modal-backdrop.is-open'))document.body.classList.remove('modal-open');}
  document.querySelectorAll('[data-photo-src]').forEach(button=>button.addEventListener('click',()=>{
    if(!viewer)return;viewer.querySelector('img').src=button.dataset.photoSrc;viewer.querySelector('img').alt=button.dataset.photoCaption||'Photo';
    viewer.querySelector('p').textContent=button.dataset.photoCaption||'';viewer.classList.add('is-open');viewer.setAttribute('aria-hidden','false');document.body.classList.add('modal-open');
  }));
  viewer?.querySelector('.viewer-close')?.addEventListener('click',closeViewer);
  viewer?.addEventListener('click',event=>{if(event.target===viewer)closeViewer();});

})();
