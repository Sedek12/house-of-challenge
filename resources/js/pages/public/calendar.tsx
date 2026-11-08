import { SlotDto, SlotPicker } from '@/components/hoc';
import { Button } from '@/components/ui/button';
import SiteLayout from '@/layouts/site-layout';
import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';

export default function Calendar({ slots }: { slots: SlotDto[] }) {
    const [mode, setMode] = useState<string>('');
    const filtered = mode ? slots.filter((s) => s.modes.includes(mode)) : slots;
    return (
        <SiteLayout title="Calendrier des passages">
            <Head title="Calendrier" />
            <div className="mb-4 flex gap-2">
                {[['', 'Tous'], ['SUR_PLACE', 'Sur place'], ['A_DISTANCE', 'À distance']].map(([v, l]) => (
                    <button key={v} onClick={() => setMode(v)} className={`rounded-md border px-3 py-1 text-sm ${mode === v ? 'bg-[#B05A26] text-white' : 'bg-white'}`}>{l}</button>
                ))}
            </div>
            <SlotPicker slots={filtered} />
            <Button asChild className="mt-6"><Link href={route('reserve', mode ? { mode } : {})}>Réserver mon passage</Link></Button>
        </SiteLayout>
    );
}
