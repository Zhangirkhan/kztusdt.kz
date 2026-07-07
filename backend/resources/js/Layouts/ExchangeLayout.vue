<script setup>
import ExchangeLayout from '@/widgets/exchange-shell/ui/ExchangeLayout.vue';

defineOptions({ inheritAttrs: false });
</script>

<template>
    <ExchangeLayout v-bind="$attrs">
        <template v-for="(_, name) in $slots" #[name]="slotData">
            <slot :name="name" v-bind="slotData ?? {}" />
        </template>
    </ExchangeLayout>
</template>
