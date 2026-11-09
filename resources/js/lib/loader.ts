import { router } from '@inertiajs/react';

/**
 * Écran de chargement (voir resources/views/app.blade.php) : logo avec un cercle qui tourne.
 * - Au chargement ou rechargement d'une page, il est déjà à l'écran ; on le retire une fois l'application affichée,
 *   après au moins 600 ms pour que l'animation soit perçue (aucun délai artificiel pour les tests automatisés).
 * - Entre deux pages (navigation), il réapparaît seulement si la page tarde (plus de 250 ms).
 */
const MIN_VISIBLE_MS = typeof navigator !== 'undefined' && navigator.webdriver ? 0 : 600;
const SLOW_NAVIGATION_MS = 250;

const el = () => document.getElementById('app-loader');

function hide() {
    el()?.classList.add('is-hidden');
}

export function hideLoaderAfterFirstPaint() {
    requestAnimationFrame(() =>
        requestAnimationFrame(() => {
            // performance.now() = temps écoulé depuis le début du chargement de la page.
            setTimeout(hide, Math.max(0, MIN_VISIBLE_MS - performance.now()));
        }),
    );
}

export function showLoaderOnSlowNavigation() {
    let timer: number | undefined;
    router.on('start', (event) => {
        const visit = event.detail.visit;
        // Seulement pour un vrai changement de page : pas pour les actualisations partielles ni les actions sur place.
        if (visit.method !== 'get' || visit.preserveState || (visit.only?.length ?? 0) > 0) return;
        timer = window.setTimeout(() => el()?.classList.remove('is-hidden'), SLOW_NAVIGATION_MS);
    });
    router.on('finish', () => {
        window.clearTimeout(timer);
        hide();
    });
}