// ==========================================================================
// OneLara — main.js
// ==========================================================================

// ---- Mobile Navigation -----------------------------------------------
const hamburger = document.querySelector('.hamburger');
const navMenu = document.querySelector('.nav-menu');

function toggleMenu() {
    hamburger.classList.toggle('active');
    navMenu.classList.toggle('active');
}

if (hamburger && navMenu) {
    hamburger.addEventListener('click', toggleMenu);
    hamburger.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            toggleMenu();
        }
    });

    // Close the mobile menu after a link is tapped
    navMenu.querySelectorAll('a').forEach(link => {
        link.addEventListener('click', () => {
            hamburger.classList.remove('active');
            navMenu.classList.remove('active');
        });
    });
}

// ---- Smooth scrolling for in-page anchors -----------------------------
document.querySelectorAll('a[href^="#"]').forEach(anchor => {
    anchor.addEventListener('click', function (e) {
        const targetId = this.getAttribute('href');
        const target = document.querySelector(targetId);
        if (target) {
            e.preventDefault();
            target.scrollIntoView({ behavior: 'smooth' });
        }
    });
});

// ---- Navbar shrink/solidify on scroll ----------------------------------
const navbar = document.querySelector('.navbar');
if (navbar) {
    const onScroll = () => {
        navbar.classList.toggle('scrolled', window.scrollY > 40);
    };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
}

// ---- Scroll-reveal animation -------------------------------------------
const revealEls = document.querySelectorAll('.reveal');
if (revealEls.length) {
    if ('IntersectionObserver' in window) {
        const revealObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('show');
                    revealObserver.unobserve(entry.target);
                }
            });
        }, { threshold: 0.15 });

        revealEls.forEach(el => revealObserver.observe(el));
    } else {
        // Fallback: no IntersectionObserver support, just show everything
        revealEls.forEach(el => el.classList.add('show'));
    }
}

// ---- Map ----------------------------------------------------------------
// Coordinates are provided by home.php via the ONELARA_COORDS global,
// falling back to Barangay Lara's coordinates if not set.
function initMap() {
    const mapEl = document.getElementById('map');
    if (!mapEl || typeof L === 'undefined') return;

    const coords = (typeof ONELARA_COORDS !== 'undefined') ? ONELARA_COORDS : [15.0876516, 120.617113];

    const map = L.map('map').setView(coords, 15);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        maxZoom: 19
    }).addTo(map);

    L.marker(coords)
        .addTo(map)
        .bindPopup('<strong>Barangay Lara</strong><br>San Fernando, Pampanga')
        .openPopup();
}

// ---- "View Map" modal ----------------------------------------------------
// Opens a larger, focused map in a modal. The modal map is created lazily
// (only on first open) since Leaflet can't measure a hidden container.
let modalMap = null;

function openMapModal() {
    const modal = document.getElementById('mapModal');
    if (!modal) return;

    modal.classList.add('open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';

    const coords = (typeof ONELARA_COORDS !== 'undefined') ? ONELARA_COORDS : [15.0876516, 120.617113];

    if (!modalMap && typeof L !== 'undefined') {
        modalMap = L.map('mapModalCanvas').setView(coords, 16);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 19
        }).addTo(modalMap);

        L.marker(coords)
            .addTo(modalMap)
            .bindPopup('<strong>Barangay Lara</strong><br>San Fernando, Pampanga')
            .openPopup();
    }

    // Leaflet needs a resize nudge once its container is actually visible.
    setTimeout(() => {
        if (modalMap) modalMap.invalidateSize();
    }, 150);
}

function closeMapModal() {
    const modal = document.getElementById('mapModal');
    if (!modal) return;

    modal.classList.remove('open');
    modal.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
}

const viewMapBtn = document.getElementById('viewMapBtn');
if (viewMapBtn) {
    viewMapBtn.addEventListener('click', openMapModal);
}

document.querySelectorAll('[data-close-map]').forEach(el => {
    el.addEventListener('click', closeMapModal);
});

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeMapModal();
});

// ---- Init ------------------------------------------------------------
document.addEventListener('DOMContentLoaded', function () {
    initMap();
});
