export type Country = { code: string; name: string; dial: string };

/** Drapeau (émoji) à partir du code pays ISO à deux lettres : « TG » → 🇹🇬. */
export const flag = (code: string) => String.fromCodePoint(...[...code.toUpperCase()].map((c) => 0x1f1e6 + c.charCodeAt(0) - 65));

/** Numéro international (E.164) à partir de l'indicatif et du numéro saisi : « +228 » + « 90 12 34 56 » → « +22890123456 ». */
export function buildPhone(dial: string, national: string): string {
    const digits = national.replace(/\D/g, '').replace(/^0+/, '');
    return digits ? `${dial}${digits}` : '';
}

/** Sépare un numéro international en pays + numéro national (pour préremplir depuis le compte). */
export function splitPhone(e164: string | null | undefined, countries: Country[], preferred: string): { code: string; national: string } {
    if (!e164) return { code: preferred, national: '' };
    const pref = countries.find((c) => c.code === preferred);
    const match = pref && e164.startsWith(pref.dial) ? pref : [...countries].sort((a, b) => b.dial.length - a.dial.length).find((c) => e164.startsWith(c.dial));
    return match ? { code: match.code, national: e164.slice(match.dial.length) } : { code: preferred, national: e164.replace(/^\+/, '') };
}
