const LOME = 'Africa/Lome';

export const fcfa = (n: number | string) => `${new Intl.NumberFormat('fr-FR').format(Number(n)).replace(/[\u202f\u00a0]/g, ' ')} FCFA`;

const full = (tz: string): Intl.DateTimeFormatOptions => ({
    weekday: 'long', day: 'numeric', month: 'long', year: 'numeric', hour: '2-digit', minute: '2-digit', timeZone: tz,
});

/** « samedi 14 novembre 2026 à 19 h 00 (heure de Lomé) » */
export function fmtLome(iso: string | null | undefined) {
    if (!iso) return '—';
    const s = new Date(iso).toLocaleString('fr-FR', full(LOME)).replace(':', ' h ');
    return `${s.replace(/(\d{4}) (\d)/, '$1 à $2')} (heure de Lomé)`;
}

export const fmtDay = (iso: string, tz = LOME) =>
    new Date(iso).toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long', timeZone: tz });

export const fmtTime = (iso: string, tz = LOME) =>
    new Date(iso).toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit', timeZone: tz }).replace(':', ' h ');

export const dayKey = (iso: string) => new Date(iso).toLocaleDateString('en-CA', { timeZone: LOME });

/** Heure locale du navigateur, uniquement si le fuseau diffère de Lomé. */
export function localHint(iso: string) {
    const tz = Intl.DateTimeFormat().resolvedOptions().timeZone;
    const a = new Date(iso).toLocaleString('fr-FR', { hour: '2-digit', minute: '2-digit', day: 'numeric', timeZone: tz });
    const b = new Date(iso).toLocaleString('fr-FR', { hour: '2-digit', minute: '2-digit', day: 'numeric', timeZone: LOME });
    return a === b ? null : `${fmtTime(iso, tz)} chez vous (${tz})`;
}

/** « https://www.tiktok.com/@houseofchallenge » → « @houseofchallenge » (chaîne vide si l'adresse n'a pas ce format). */
export const tiktokHandle = (url?: string | null) => /tiktok\.com\/(@[^/?#]+)/i.exec(url ?? '')?.[1] ?? '';

export const MODE_LABEL: Record<string, string> = { SUR_PLACE: 'Sur place (Togo)', A_DISTANCE: 'À distance (TikTok)' };

export const STATUS_LABEL: Record<string, string> = {
    EN_ATTENTE_PAIEMENT: 'En attente de paiement', CONFIRMEE: 'Confirmée', EFFECTUEE: 'Effectuée', ABSENT: 'Absent',
    ANNULEE: 'Annulée', ANNULEE_PAR_ORGANISATEUR: 'Annulée par l’organisation',
    EN_ATTENTE: 'En attente', PAYE: 'Payé', ECHOUE: 'Échoué', ANNULE: 'Annulé', REMBOURSE: 'Remboursé',
    PUBLIEE: 'Publiée', REFUSEE: 'Refusée', EXPIREE: 'Expirée', DEPUBLIEE: 'Dépubliée',
    DISPONIBLE: 'Disponible', RESERVE: 'Réservé', BLOQUE: 'Bloqué', PROGRAMMEE: 'Programmée', TERMINEE: 'Terminée',
};
