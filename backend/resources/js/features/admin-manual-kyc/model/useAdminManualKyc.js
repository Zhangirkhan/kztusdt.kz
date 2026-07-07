import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

export function useAdminManualKyc(user) {
    const isLegalEntity = computed(() => user.client_type === 'legal_entity');

    const kyc = user.kyc_profile ?? null;

    const form = useForm({
        company_name: kyc?.company_name ?? user.company_name ?? '',
        first_name: kyc?.first_name ?? '',
        last_name: kyc?.last_name ?? '',
        document_type: kyc?.document_type ?? (user.client_type === 'legal_entity' ? 'registration' : 'id_card'),
        document_number: kyc?.document_number ?? (user.client_type === 'legal_entity' ? (user.bin ?? '') : ''),
        comment: '',
    });

    function submit(callbacks = {}) {
        form.post(route('admin.users.kyc.manual-approve', user.id), {
            preserveScroll: true,
            ...callbacks,
        });
    }

    return { form, submit, isLegalEntity };
}
