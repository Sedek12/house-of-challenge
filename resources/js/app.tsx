import '../css/app.css';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';
import { route as routeFn } from 'ziggy-js';
import { hideLoaderAfterFirstPaint, showLoaderOnSlowNavigation } from './lib/loader';

declare global {
    const route: typeof routeFn;
}

/** Vrai si l'appareil affiche les drapeaux en émoji (Android, iOS, macOS) ; faux sur Windows, qui montre « CH » au lieu du drapeau. */
function supportsFlagEmoji(): boolean {
    try {
        const ctx = document.createElement('canvas').getContext('2d', { willReadFrequently: true });
        if (!ctx) return true;
        ctx.canvas.width = ctx.canvas.height = 24;
        ctx.textBaseline = 'top';
        ctx.font = '20px system-ui, sans-serif';
        ctx.fillText('\u{1F1E8}\u{1F1ED}', 0, 0); // drapeau suisse : rouge s'il est dessiné
        const px = ctx.getImageData(0, 0, 24, 24).data;
        for (let i = 0; i < px.length; i += 4) if (px[i] > 180 && px[i + 1] < 80 && px[i + 2] < 80 && px[i + 3] > 200) return true;
        return false;
    } catch {
        return true;
    }
}
if (!supportsFlagEmoji()) document.documentElement.classList.add('needs-flag-font');

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) => resolvePageComponent(`./pages/${name}.tsx`, import.meta.glob('./pages/**/*.tsx')),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(<App {...props} />);
        hideLoaderAfterFirstPaint();
        showLoaderOnSlowNavigation();
    },
    progress: {
        color: '#EE6400',
    },
});

// Thème clair uniquement (charte graphique d'OCD Live) : on retire toute classe « dark » éventuelle.
document.documentElement.classList.remove('dark');
