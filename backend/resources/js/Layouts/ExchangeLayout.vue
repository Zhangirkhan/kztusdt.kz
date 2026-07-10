<script setup>
import ExchangeLayout from '@/widgets/exchange-shell/ui/ExchangeLayout.vue';

defineOptions({ inheritAttrs: false });

defineProps({
    showBrand: {
        type: Boolean,
        default: true,
    },
});
</script>

<template>
    <ExchangeLayout v-bind="$attrs" :show-brand="showBrand">
        <template v-for="(_, name) in $slots" #[name]="slotData">
            <slot :name="name" v-bind="slotData ?? {}" />
        </template>
    </ExchangeLayout>
</template>
