import { useForm } from '@inertiajs/vue3';
import { computed } from 'vue';

export function useAdminManualKyc(user) {
    const isLegalEntity = computed(() => user.client_type === 'legal_entity');

    const form = useForm({
        company_name: user.company_name ?? user.name ?? '',
        first_name: user.kyc_profile?.name?.split(' ')[0] ?? user.name?.split(' ')[0] ?? '',
        last_name: user.kyc_profile?.name?.split(' ').slice(1).join(' ') ?? user.name?.split(' ').slice(1).join(' ') ?? '',
        document_type: user.client_type === 'legal_entity' ? 'registration' : 'id_card',
        document_number: user.client_type === 'legal_entity' ? (user.bin ?? '') : '',
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
