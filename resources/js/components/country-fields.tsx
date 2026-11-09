import { inputCls } from '@/components/hoc';
import { Country, flag } from '@/lib/countries';

/** Liste de pays : un clic ouvre la liste avec le drapeau, le nom et l'indicatif ; taper les premières lettres suffit à trouver un pays. */
export function CountrySelect({ id, value, onChange, countries }: { id?: string; value: string; onChange: (code: string) => void; countries: Country[] }) {
    return (
        <select id={id} className={`${inputCls} flag-font`} value={value} onChange={(e) => onChange(e.target.value)}>
            {countries.map((c) => (
                <option key={c.code} value={c.code}>{flag(c.code)} {c.name} ({c.dial})</option>
            ))}
        </select>
    );
}

/** Téléphone avec choix du pays : le drapeau et l'indicatif sont ajoutés automatiquement au numéro. */
export function PhoneInput({ id = 'phone', label, countries, code, national, onChange, error }: {
    id?: string; label: string; countries: Country[]; code: string; national: string; onChange: (code: string, national: string) => void; error?: string;
}) {
    return (
        <div className="grid gap-1 text-sm">
            <label htmlFor={id} className="font-medium">{label}</label>
            <div className="flex gap-2">
                <select aria-label="Pays et indicatif du téléphone" className={`${inputCls} flag-font !w-auto max-w-[8.5rem] shrink-0`} value={code} onChange={(e) => onChange(e.target.value, national)}>
                    {countries.map((c) => (
                        <option key={c.code} value={c.code}>{flag(c.code)} {c.dial} {c.name}</option>
                    ))}
                </select>
                <input id={id} type="tel" inputMode="tel" autoComplete="tel-national" placeholder="90 12 34 56" className={inputCls} value={national} onChange={(e) => onChange(code, e.target.value)} />
            </div>
            {error && <span className="text-red-600">{error}</span>}
        </div>
    );
}
