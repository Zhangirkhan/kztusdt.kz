<script setup>
import ExchangeLayout from '@/Layouts/ExchangeLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { defineAsyncComponent } from 'vue';

const SumsubKycWidget = defineAsyncComponent(() => import('@/Components/SumsubKycWidget.vue'));

const props = defineProps({
    profile: Object,
    kycStatus: String,
    rejectionReason: String,
    provider: { type: String, default: 'manual' },
    aituVerifyUrl: { type: String, default: null },
});

const page = usePage();
const useSumsub = props.provider === 'sumsub' && !['approved'].includes(props.kycStatus);
const useAitu = props.provider === 'aitu' && props.kycStatus !== 'approved';

const form = useForm({
    first_name: props.profile?.first_name ?? '',
    last_name: props.profile?.last_name ?? '',
    document_type: props.profile?.document_type ?? 'id_card',
    document_number: props.profile?.document_number ?? '',
    id_front: null,
    id_back: null,
    selfie: null,
});

const canEdit = !['approved', 'pending_review'].includes(props.kycStatus) && props.provider !== 'aitu';

function submit() {
    form.post(route('kyc.store'), { forceFormData: true });
}

function onFile(field, event) {
    form[field] = event.target.files[0] ?? null;
}
</script>

<template>
    <Head title="KYC" />

    <ExchangeLayout>
        <template #title>KYC верификация</template>

        <div v-if="page.props.flash?.success" class="card mb-4 border border-accent/30 text-accent">
            {{ page.props.flash.success }}
        </div>

        <section class="card mb-stack-element">
            <p class="text-label-caps uppercase text-text-dim">Статус</p>
            <p class="mt-2 text-headline-md capitalize text-accent">{{ kycStatus }}</p>
            <p v-if="rejectionReason" class="mt-2 text-sm text-error">{{ rejectionReason }}</p>
            <p v-if="kycStatus === 'pending_review'" class="mt-2 text-body-sm text-text-muted">
                Документы на проверке. Обычно Sumsub отвечает за 1–2 минуты.
            </p>
        </section>

        <section v-if="useSumsub" class="card">
            <SumsubKycWidget container-id="kyc-page-sumsub" :kyc-status="kycStatus" />
        </section>

        <section v-else-if="useAitu" class="card space-y-4">
            <p class="text-body-sm text-text-muted">
                Верификацию личности проводит Aitu Passport. Нажмите кнопку — вы перейдёте в Aitu,
                подтвердите личность, и после возврата ваш статус обновится автоматически.
            </p>
            <a v-if="aituVerifyUrl" :href="aituVerifyUrl" class="btn-primary inline-block">
                Пройти верификацию через Aitu
            </a>
        </section>

        <form v-else-if="canEdit" class="space-y-stack-element" @submit.prevent="submit">
            <div>
                <label class="mb-2 block text-label-caps uppercase text-text-dim">Имя</label>
                <input v-model="form.first_name" class="input-field" required />
            </div>
            <div>
                <label class="mb-2 block text-label-caps uppercase text-text-dim">Фамилия</label>
                <input v-model="form.last_name" class="input-field" required />
            </div>
            <div>
                <label class="mb-2 block text-label-caps uppercase text-text-dim">Тип документа</label>
                <select v-model="form.document_type" class="input-field">
                    <option value="id_card">Удостоверение</option>
                    <option value="passport">Паспорт</option>
                </select>
            </div>
            <div>
                <label class="mb-2 block text-label-caps uppercase text-text-dim">Номер документа</label>
                <input v-model="form.document_number" class="input-field" required />
            </div>

            <div class="space-y-3">
                <label class="block text-label-caps uppercase text-text-dim">Документы</label>
                <label class="card block cursor-pointer">
                    <span class="text-sm">Лицевая сторона</span>
                    <input type="file" accept="image/*" class="mt-2 block w-full text-sm" @change="onFile('id_front', $event)" />
                </label>
                <label class="card block cursor-pointer">
                    <span class="text-sm">Обратная сторона</span>
                    <input type="file" accept="image/*" class="mt-2 block w-full text-sm" @change="onFile('id_back', $event)" />
                </label>
                <label class="card block cursor-pointer">
                    <span class="text-sm">Селфи с документом</span>
                    <input type="file" accept="image/*" class="mt-2 block w-full text-sm" @change="onFile('selfie', $event)" />
                </label>
            </div>

            <p v-if="form.errors.form" class="text-sm text-error">{{ form.errors.form }}</p>

            <button type="submit" class="btn-primary" :disabled="form.processing">
                Отправить на проверку
            </button>
        </form>

        <div v-else-if="kycStatus === 'approved'" class="card text-accent">
            KYC одобрен. Кошелёк будет доступен после создания адреса.
            <Link href="/wallet" class="mt-3 block font-semibold">Перейти в кошелёк →</Link>
        </div>
    </ExchangeLayout>
</template>
