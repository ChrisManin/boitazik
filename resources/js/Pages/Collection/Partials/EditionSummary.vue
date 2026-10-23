<script setup lang="ts">
import { MusicBrainzEdition } from '@/types';
import AlbumSummary from './AlbumSummary.vue';
import { computed } from 'vue';

const props = defineProps<{
    edition: MusicBrainzEdition;
}>();

// What tells two pressings of the same album apart
const details = computed(() =>
    [props.edition.format, props.edition.label, props.edition.catalog_number, props.edition.country, props.edition.year]
        .filter(Boolean)
        .join(' · ')
);
</script>

<template>
    <div class="min-w-0 space-y-1">
        <AlbumSummary :album="edition.album" />
        <p class="text-left text-sm text-gray-700 dark:text-gray-300">{{ details }}</p>
    </div>
</template>
