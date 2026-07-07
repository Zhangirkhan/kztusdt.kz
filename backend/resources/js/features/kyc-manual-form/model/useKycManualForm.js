import { useForm } from '@inertiajs/vue3';

export function useKycManualForm(profile) {
    const form = useForm({
        first_name: profile?.first_name ?? '',
        last_name: profile?.last_name ?? '',
        document_type: profile?.document_type ?? 'id_card',
        document_number: profile?.document_number ?? '',
        id_front: null,
        id_back: null,
        selfie: null,
    });

    function submit() {
        form.post(route('kyc.store'), { forceFormData: true });
    }

    function onFile(field, event) {
        form[field] = event.target.files[0] ?? null;
    }

    return { form, submit, onFile };
}
