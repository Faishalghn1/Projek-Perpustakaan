'use strict';

/// Ambil kontrol menu agar navigasi ponsel dapat dibuka dan ditutup.
const menuToggle = document.querySelector('.menu-toggle');
const siteNav = document.querySelector('#site-nav');

if (menuToggle && siteNav) {
    /// Sinkronkan status aksesibilitas tombol dengan visibilitas navigasi.
    menuToggle.addEventListener('click', () => {
        const isExpanded = menuToggle.getAttribute('aria-expanded') === 'true';
        menuToggle.setAttribute('aria-expanded', String(!isExpanded));
        menuToggle.setAttribute('aria-label', isExpanded ? 'Buka menu' : 'Tutup menu');
        siteNav.classList.toggle('is-open', !isExpanded);
    });
}

document.querySelectorAll('form[novalidate]').forEach((form) => {
    /// Tampilkan validasi bawaan browser sebelum formulir dikirim.
    form.addEventListener('submit', (event) => {
        if (!form.checkValidity()) {
            event.preventDefault();
            form.reportValidity();
        }
    });
});