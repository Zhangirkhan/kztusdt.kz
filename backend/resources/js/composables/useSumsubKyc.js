import { onUnmounted, ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { localizedPath } from '@/utils/localizedPath';

export function useSumsubKyc(options = {}) {
    const error = ref('');
    const loading = ref(false);
    const notice = ref('');
    const currentStep = ref('');

    let syncTimer = null;
    let launched = false;

    async function fetchSumsubToken() {
        const response = await fetch(localizedPath('/kyc/sumsub/token'), {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                Accept: 'application/json',
            },
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.error ?? 'Не удалось получить токен проверки');
        }

        return data.token;
    }

    async function syncSumsubStatus() {
        const response = await fetch(localizedPath('/kyc/sumsub/sync'), {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
                Accept: 'application/json',
            },
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(data.error ?? 'Не удалось обновить статус проверки');
        }

        if (data.kyc_status === 'approved') {
            notice.value = 'Верификация одобрена.';
            options.onApproved?.(data);
        } else if (data.kyc_status === 'rejected') {
            notice.value = '';
            options.onRejected?.(data);
            router.reload({ only: options.reloadOnly ?? ['profile', 'kycStatus', 'rejectionReason', 'provider', 'userStatus', 'kyc'] });
        } else if (data.kyc_status === 'pending_review') {
            notice.value = 'Документы отправлены на проверку. Обычно это занимает 1–2 минуты.';
            options.onPending?.(data);
        }

        return data;
    }

    function scheduleStatusSync() {
        if (syncTimer) {
            clearInterval(syncTimer);
        }

        syncTimer = window.setInterval(async () => {
            try {
                await syncSumsubStatus();
            } catch {
                // Ignore transient sync errors while Sumsub is still processing.
            }
        }, 5000);
    }

    function loadSumsubScript() {
        return new Promise((resolve, reject) => {
            if (window.snsWebSdk) {
                resolve();

                return;
            }

            const script = document.createElement('script');
            script.src = 'https://static.sumsub.com/idensic/static/sns-websdk-builder.js';
            script.onload = resolve;
            script.onerror = () => reject(new Error('Не удалось загрузить Sumsub SDK'));
            document.head.appendChild(script);
        });
    }

    function mapSumsubError(err) {
        const reason = String(err?.reason ?? err?.code ?? err?.message ?? err ?? '');

        if (/permission/i.test(reason)) {
            return 'Нет доступа к камере. Разрешите камеру для kztusdt.kz в настройках браузера и обновите страницу.';
        }

        return reason || 'Ошибка проверки';
    }

    function mapStepLabel(step) {
        if (step === 'IDENTITY' || step === 'IDENTITY2') {
            return 'Сфотографируйте удостоверение';
        }

        if (step === 'SELFIE' || step === 'LIVENESS') {
            return 'Видео-подтверждение: следуйте подсказкам на экране';
        }

        return '';
    }

    async function launch(containerId) {
        if (launched) {
            return;
        }

        launched = true;
        loading.value = true;
        error.value = '';

        try {
            await loadSumsubScript();
            const token = await fetchSumsubToken();

            window.snsWebSdk
                .init(token, () => fetchSumsubToken())
                .withConf({ lang: 'ru' })
                .withOptions({ addViewportTag: false, adaptIframeHeight: true })
                .on('idCheck.onError', (err) => {
                    error.value = mapSumsubError(err);
                })
                .on('idCheck.onStepCompleted', (payload) => {
                    const label = mapStepLabel(payload?.idDocSetType ?? payload?.step ?? '');

                    if (label) {
                        currentStep.value = label;
                    }
                })
                .on('idCheck.onApplicantSubmitted', () => {
                    notice.value = 'Документы и видео отправлены. Обновляем статус…';
                    scheduleStatusSync();
                    syncSumsubStatus().catch(() => {});
                })
                .on('idCheck.onApplicantStatusChanged', () => {
                    syncSumsubStatus().catch(() => {});
                })
                .build()
                .launch(`#${containerId}`);
        } catch (err) {
            error.value = err?.message ?? 'Не удалось запустить проверку';
            launched = false;
        } finally {
            loading.value = false;
        }
    }

    onUnmounted(() => {
        if (syncTimer) {
            clearInterval(syncTimer);
        }
    });

    return {
        error,
        loading,
        notice,
        currentStep,
        launch,
        syncSumsubStatus,
    };
}
