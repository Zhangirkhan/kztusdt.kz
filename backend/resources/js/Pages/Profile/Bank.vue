<script setup>
import ExchangeLayout from '@/widgets/exchange-shell/ui/ExchangeLayout.vue';
import ProfileSettingsShell from '@/widgets/profile-settings-shell/ui/ProfileSettingsShell.vue';
import FlashBanner from '@/shared/ui/flash-banner/FlashBanner.vue';
import BankLogo from '@/shared/ui/bank-logo/BankLogo.vue';
import { formatKzIban, isKzIbanComplete } from '@/utils/accountMask';
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';
import { useI18n } from 'vue-i18n';

const props = defineProps({
    profile: Object,
    banks: { type: Array, default: () => [] },
    cards: { type: Array, default: () => [] },
});

const page = usePage();
const { t } = useI18n();

const showForm = ref(false);
const editingCardId = ref(null);
const renamingCardId = ref(null);
const renameValue = ref('');
const deletingCardId = ref(null);

function bikForBank(code) {
    return props.banks.find((bank) => bank.code === code)?.bik ?? '';
}

function emptyFormState() {
    const bankCode = props.banks[0]?.code ?? 'kaspi';

    return {
        bank_code: bankCode,
        bik: bikForBank(bankCode),
        holder_name: '',
        iin: props.profile?.iin ?? '',
        iban: formatKzIban(''),
    };
}

const form = useForm(emptyFormState());

const formTitle = computed(() => (editingCardId.value ? t('bank.editCard') : t('bank.addCard')));

const selectedBankName = computed(() => bankName(form.bank_code));

const canSubmitCard = computed(() => {
    const hasIban = isKzIbanComplete(form.iban);
    const hasBik = /^[A-Za-z0-9]{8}$/.test(String(form.bik ?? ''));
    const hasMeta = form.bank_code && form.holder_name.trim();
    const hasIin = /^\d{12}$/.test(String(form.iin ?? ''));

    return hasMeta && hasIin && hasBik && hasIban && !form.processing;
});

watch(
    () => props.cards,
    () => {
        if (editingCardId.value && !props.cards.some((card) => card.id === editingCardId.value)) {
            cancelForm();
        }
    },
);

function openCreateForm() {
    editingCardId.value = null;
    form.clearErrors();
    const next = emptyFormState();
    form.defaults(next);
    Object.assign(form, next);
    showForm.value = true;
}

function openEditForm(card) {
    editingCardId.value = card.id;
    form.clearErrors();
    const next = {
        bank_code: card.bank_code,
        bik: card.bik ?? bikForBank(card.bank_code),
        holder_name: card.holder_name ?? '',
        iin: props.profile?.iin ?? '',
        iban: card.iban ? formatKzIban(card.iban) : formatKzIban(''),
    };
    form.defaults(next);
    Object.assign(form, next);
    showForm.value = true;
}

function cancelForm() {
    showForm.value = false;
    editingCardId.value = null;
    form.clearErrors();
    Object.assign(form, emptyFormState());
}

function selectBank(code) {
    form.bank_code = code;
    form.bik = bikForBank(code);
    form.clearErrors('bank_code');
    form.clearErrors('bik');
}

function onIbanInput(event) {
    const next = formatKzIban(event.target.value);
    form.iban = next;
    event.target.value = next;
}

function submitCard() {
    if (editingCardId.value) {
        form.patch(route('profile.bank.cards.update', editingCardId.value), {
            preserveScroll: true,
            onSuccess: () => cancelForm(),
        });
        return;
    }

    form.post(route('profile.bank.cards.store'), {
        preserveScroll: true,
        onSuccess: () => cancelForm(),
    });
}

function startRename(card) {
    renamingCardId.value = card.id;
    renameValue.value = card.label;
}

function cancelRename() {
    renamingCardId.value = null;
    renameValue.value = '';
}

function submitRename(card) {
    const label = renameValue.value.trim();

    if (!label) {
        return;
    }

    router.patch(
        route('profile.bank.cards.rename', card.id),
        { label },
        {
            preserveScroll: true,
            onSuccess: () => cancelRename(),
        },
    );
}

function confirmDelete(card) {
    deletingCardId.value = card.id;
}

function cancelDelete() {
    deletingCardId.value = null;
}

function destroyCard(card) {
    router.delete(route('profile.bank.cards.destroy', card.id), {
        preserveScroll: true,
        onSuccess: () => {
            cancelDelete();
            if (editingCardId.value === card.id) {
                cancelForm();
            }
        },
    });
}

function bankName(code) {
    return props.banks.find((bank) => bank.code === code)?.name ?? code;
}
</script>

<template>
    <Head :title="t('bank.title')" />

    <ExchangeLayout>
        <template #title>{{ t('bank.title') }}</template>

        <ProfileSettingsShell>
            <FlashBanner v-if="page.props.flash?.success" :message="page.props.flash.success" tone="success" />

            <div v-if="cards.length === 0 && !showForm" class="card bank-empty mb-4">
                <div class="bank-empty__icon">
                    <span class="material-symbols-outlined">credit_card</span>
                </div>
                <p class="font-semibold text-lg">{{ t('bank.emptyTitle') }}</p>
                <p class="mt-2 text-sm text-text-muted">
                    {{ t('bank.emptyHint') }}
                </p>
                <button type="button" class="btn-primary mt-4" @click="openCreateForm">{{ t('bank.addCard') }}</button>
            </div>

            <div v-else class="mb-4 space-y-3">
                <article
                    v-for="card in cards"
                    :key="card.id"
                    class="bank-card"
                >
                    <div class="flex items-start gap-3">
                        <BankLogo :code="card.bank_code" />
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <h3 class="truncate font-semibold">{{ card.label }}</h3>
                                <span class="verified-badge">{{ card.bank_name }}</span>
                            </div>
                            <p class="mt-1 text-sm text-text-dim">{{ card.holder_name }}</p>
                            <div class="mt-2 space-y-1 text-sm text-text-muted">
                                <p v-if="card.bik" class="flex items-center gap-2 font-mono">
                                    <span class="material-symbols-outlined text-base text-accent">tag</span>
                                    {{ t('bank.bikLabel', { bik: card.bik }) }}
                                </p>
                                <p v-if="card.iban_masked" class="flex items-center gap-2 font-mono">
                                    <span class="material-symbols-outlined text-base text-accent">account_balance</span>
                                    {{ card.iban_masked }}
                                </p>
                                <p v-if="card.phone_masked" class="flex items-center gap-2">
                                    <span class="material-symbols-outlined text-base text-accent">call</span>
                                    {{ card.phone_masked }}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div v-if="renamingCardId === card.id" class="mt-3 space-y-2">
                        <input v-model="renameValue" class="input-field" maxlength="255" :placeholder="t('bank.cardNamePlaceholder')" />
                        <div class="flex gap-2">
                            <button type="button" class="btn-primary flex-1" @click="submitRename(card)">{{ t('common.save') }}</button>
                            <button type="button" class="btn-secondary flex-1" @click="cancelRename">{{ t('common.cancel') }}</button>
                        </div>
                    </div>

                    <div v-else-if="deletingCardId === card.id" class="mt-3 space-y-2">
                        <p class="text-sm text-error">{{ t('bank.deleteConfirm', { label: card.label }) }}</p>
                        <div class="flex gap-2">
                            <button type="button" class="btn-primary flex-1" @click="destroyCard(card)">{{ t('bank.delete') }}</button>
                            <button type="button" class="btn-secondary flex-1" @click="cancelDelete">{{ t('common.cancel') }}</button>
                        </div>
                    </div>

                    <div v-else class="bank-card__actions mt-3">
                        <button type="button" class="bank-card__action" @click="startRename(card)">
                            <span class="material-symbols-outlined text-base">edit</span>
                            {{ t('bank.rename') }}
                        </button>
                        <button type="button" class="bank-card__action" @click="openEditForm(card)">
                            <span class="material-symbols-outlined text-base">tune</span>
                            {{ t('bank.edit') }}
                        </button>
                        <button type="button" class="bank-card__action bank-card__action--danger" @click="confirmDelete(card)">
                            <span class="material-symbols-outlined text-base">delete</span>
                            {{ t('bank.delete') }}
                        </button>
                    </div>
                </article>

                <button v-if="!showForm" type="button" class="btn-secondary w-full" @click="openCreateForm">
                    {{ t('bank.addAnother') }}
                </button>
            </div>

            <form v-if="showForm" class="space-y-4" @submit.prevent="submitCard">
                <section class="card space-y-5">
                    <div>
                        <p class="text-label-caps uppercase text-text-dim">{{ formTitle }}</p>
                        <p class="mt-1 text-sm text-text-muted">{{ t('bank.formHint') }}</p>
                    </div>

                    <div>
                        <label class="mb-2 block text-label-caps uppercase text-text-dim">{{ t('bank.bank') }}</label>
                        <div class="bank-picker" role="radiogroup" :aria-label="t('bank.bank')">
                            <button
                                v-for="bank in banks"
                                :key="bank.code"
                                type="button"
                                role="radio"
                                class="bank-picker__option"
                                :class="{ 'bank-picker__option--active': form.bank_code === bank.code }"
                                :aria-checked="form.bank_code === bank.code"
                                @click="selectBank(bank.code)"
                            >
                                <BankLogo :code="bank.code" size="sm" />
                                <span class="bank-picker__name">{{ bank.name }}</span>
                                <span
                                    v-if="form.bank_code === bank.code"
                                    class="material-symbols-outlined bank-picker__check"
                                    aria-hidden="true"
                                >
                                    check_circle
                                </span>
                            </button>
                        </div>
                        <p v-if="form.errors.bank_code" class="mt-2 text-sm text-error">{{ form.errors.bank_code }}</p>
                    </div>

                    <div>
                        <label class="mb-2 block text-label-caps uppercase text-text-dim">{{ t('bank.bik') }}</label>
                        <input
                            v-model="form.bik"
                            class="input-field font-mono uppercase"
                            required
                            maxlength="8"
                            autocomplete="off"
                            placeholder="CASPKZKA"
                            @input="(e) => { form.bik = e.target.value.replace(/\\s/g, '').toUpperCase().slice(0, 8); e.target.value = form.bik; }"
                        />
                        <p v-if="form.errors.bik" class="mt-2 text-sm text-error">{{ form.errors.bik }}</p>
                    </div>

                    <div>
                        <label class="mb-2 block text-label-caps uppercase text-text-dim">{{ t('bank.iban') }}</label>
                        <input
                            :value="form.iban"
                            class="input-field font-mono uppercase"
                            required
                            inputmode="text"
                            autocomplete="off"
                            maxlength="25"
                            placeholder="KZ00 0000 0000 0000 0000"
                            @input="onIbanInput"
                        />
                        <p v-if="form.errors.iban" class="mt-2 text-sm text-error">{{ form.errors.iban }}</p>
                    </div>

                    <div>
                        <label class="mb-2 block text-label-caps uppercase text-text-dim">{{ t('bank.holderName') }}</label>
                        <input
                            v-model="form.holder_name"
                            class="input-field"
                            required
                            maxlength="255"
                            :placeholder="t('bank.holderNamePlaceholder')"
                        />
                        <p v-if="form.errors.holder_name" class="mt-2 text-sm text-error">{{ form.errors.holder_name }}</p>
                    </div>

                    <div>
                        <label class="mb-2 block text-label-caps uppercase text-text-dim">{{ t('bank.iin') }}</label>
                        <input
                            v-model="form.iin"
                            class="input-field font-mono"
                            required
                            inputmode="numeric"
                            autocomplete="off"
                            maxlength="12"
                            :placeholder="t('bank.iinPlaceholder')"
                            @input="(e) => { form.iin = e.target.value.replace(/\\D/g, '').slice(0, 12); e.target.value = form.iin; }"
                        />
                        <p v-if="form.errors.iin" class="mt-2 text-sm text-error">{{ form.errors.iin }}</p>
                    </div>
                </section>

                <div class="flex gap-2">
                    <button type="submit" class="btn-primary flex-1" :disabled="!canSubmitCard">
                        {{ form.processing ? t('bank.saving') : t('bank.save') }}
                    </button>
                    <button type="button" class="btn-secondary flex-1" :disabled="form.processing" @click="cancelForm">
                        {{ t('bank.cancel') }}
                    </button>
                </div>
            </form>

            <p class="mt-4 text-sm text-text-muted">
                {{ t('bank.footerHint') }}
            </p>
        </ProfileSettingsShell>
    </ExchangeLayout>
</template>
