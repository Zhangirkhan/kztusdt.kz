<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps({
    title: {
        type: String,
        default: null,
    },
    description: {
        type: String,
        default: null,
    },
});

const page = usePage();

const seo = computed(() => {
    const base = page.props.seo ?? {};

    return {
        title: props.title ?? base.title ?? null,
        description: props.description ?? base.description ?? null,
        robots: base.robots ?? 'noindex, nofollow',
        canonical: base.canonical ?? null,
        ogTitle: props.title ?? base.ogTitle ?? base.title ?? null,
        ogDescription: props.description ?? base.ogDescription ?? base.description ?? null,
        ogUrl: base.ogUrl ?? base.canonical ?? null,
        ogImage: base.ogImage ?? null,
        ogType: base.ogType ?? 'website',
        ogSiteName: base.ogSiteName ?? page.props.company?.name ?? null,
        twitterCard: base.twitterCard ?? 'summary',
    };
});
</script>

<template>
    <Head :title="seo.title || undefined">
        <meta v-if="seo.description" head-key="description" name="description" :content="seo.description" />
        <meta head-key="robots" name="robots" :content="seo.robots" />

        <link v-if="seo.canonical" head-key="canonical" rel="canonical" :href="seo.canonical" />

        <meta v-if="seo.ogTitle" head-key="og:title" property="og:title" :content="seo.ogTitle" />
        <meta v-if="seo.ogDescription" head-key="og:description" property="og:description" :content="seo.ogDescription" />
        <meta v-if="seo.ogUrl" head-key="og:url" property="og:url" :content="seo.ogUrl" />
        <meta v-if="seo.ogType" head-key="og:type" property="og:type" :content="seo.ogType" />
        <meta v-if="seo.ogSiteName" head-key="og:site_name" property="og:site_name" :content="seo.ogSiteName" />
        <meta v-if="seo.ogImage" head-key="og:image" property="og:image" :content="seo.ogImage" />

        <meta head-key="twitter:card" name="twitter:card" :content="seo.twitterCard" />
        <meta v-if="seo.ogTitle" head-key="twitter:title" name="twitter:title" :content="seo.ogTitle" />
        <meta v-if="seo.ogDescription" head-key="twitter:description" name="twitter:description" :content="seo.ogDescription" />
    </Head>
</template>
