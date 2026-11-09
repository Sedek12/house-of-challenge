/** Données partagées par toutes les pages (voir HandleInertiaRequests). */
export type Shared = {
    auth: { user: null | { name: string; role: string } };
    site: { whatsapp: string; tiktok: string; logo: string; brand_color: string; email: string | null; studio: string };
    flash: { success?: string };
    errors: Record<string, string>;
};

/** Texte du bouton principal : blanc, sauf si la couleur choisie est très claire (lisibilité). */
export function readableOn(hex: string) {
    const m = /^#?([0-9a-f]{6})$/i.exec(hex ?? '');
    if (!m) return '#1f1f1f';
    const [r, g, b] = [0, 2, 4].map((i) => parseInt(m[1].slice(i, i + 2), 16) / 255).map((v) => (v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4));
    return 0.2126 * r + 0.7152 * g + 0.0722 * b > 0.6 ? '#1f1f1f' : '#ffffff';
}

export const ROLE_LABEL: Record<string, string> = { ADMIN: 'Administrateur', INTERVIEWER: 'Animateur', VENDOR: 'Vendeur' };

/** Mention de l'éditeur du site, affichée en bas de toutes les pages. */
export const DEVELOPER = 'Horizon Digital';
