import { PlanningRow, PlanningView } from '@/components/planning-view';
import SiteLayout from '@/layouts/site-layout';
import { Head } from '@inertiajs/react';

export default function TeamPlanning(props: { view: string; date: string; rows: PlanningRow[] }) {
    return (
        <SiteLayout portal="team" title="Planning">
            <Head title="Planning" />
            <PlanningView routeName="team.planning" {...props} />
        </SiteLayout>
    );
}
