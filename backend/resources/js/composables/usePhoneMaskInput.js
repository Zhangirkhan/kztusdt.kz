import { nextTick, ref } from 'vue';
import {
    caretForNationalDigitIndex,
    clearPhoneMask,
    nationalDigitIndexBefore,
    parseNationalDigits,
    removeNationalDigitAt,
    updatePhoneMask,
} from '@/utils/phoneMask';

/**
 * Controlled phone mask input with correct Backspace/Delete over formatting chars.
 *
 * @param {import('vue').Ref<string>} phoneRef
 * @param {{ onChange?: () => void }} [options]
 */
export function usePhoneMaskInput(phoneRef, options = {}) {
    const phoneInput = ref(null);

    function setCaret(pos) {
        nextTick(() => {
            const el = phoneInput.value;

            if (el && typeof pos === 'number') {
                el.setSelectionRange(pos, pos);
            }
        });
    }

    function syncInput() {
        if (phoneInput.value) {
            phoneInput.value.value = phoneRef.value;
        }
    }

    function onPhoneInput(event) {
        const input = event.target;
        const previous = phoneRef.value;
        const caret = input.selectionStart ?? input.value.length;
        const digitsBefore = nationalDigitIndexBefore(previous, caret);
        const wasAdding = input.value.length > previous.length;

        phoneRef.value = updatePhoneMask(previous, input.value);
        syncInput();

        const nextCaret = wasAdding
            ? caretForNationalDigitIndex(phoneRef.value, digitsBefore + 1)
            : caretForNationalDigitIndex(phoneRef.value, digitsBefore);

        setCaret(nextCaret);
        options.onChange?.();
    }

    function onPhoneKeydown(event) {
        if (event.key !== 'Backspace' && event.key !== 'Delete') {
            return;
        }

        const input = event.target;
        const { selectionStart, selectionEnd, value } = input;

        if (selectionStart === null || selectionEnd === null) {
            return;
        }

        if (selectionStart !== selectionEnd) {
            return;
        }

        if (event.key === 'Backspace') {
            if (selectionStart <= 2) {
                event.preventDefault();

                return;
            }

            if (/\D/.test(value[selectionStart - 1])) {
                event.preventDefault();
                const digitIndex = nationalDigitIndexBefore(value, selectionStart);
                phoneRef.value = removeNationalDigitAt(value, digitIndex);
                syncInput();
                setCaret(caretForNationalDigitIndex(phoneRef.value, digitIndex - 1));
                options.onChange?.();

                return;
            }

            return;
        }

        if (selectionStart >= value.length) {
            return;
        }

        if (/\D/.test(value[selectionStart])) {
            event.preventDefault();
            const digitIndex = nationalDigitIndexBefore(value, selectionStart);
            const national = parseNationalDigits(value);

            if (digitIndex >= national.length) {
                return;
            }

            phoneRef.value = removeNationalDigitAt(value, digitIndex + 1);
            syncInput();
            setCaret(caretForNationalDigitIndex(phoneRef.value, digitIndex));
            options.onChange?.();
        }
    }

    function clearPhone() {
        phoneRef.value = clearPhoneMask();
        syncInput();
        setCaret(2);
        options.onChange?.();
    }

    return {
        phoneInput,
        syncInput,
        onPhoneInput,
        onPhoneKeydown,
        clearPhone,
        setCaret,
    };
}
