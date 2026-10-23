<script setup lang="ts">
import { MusicBrainzResult } from '@/types';
import { ref, watch } from 'vue';

const props = defineProps<{
    album: MusicBrainzResult;
}>();

// The Cover Art Archive has no cover for many albums: show a placeholder instead
const coverFailed = ref(false);
watch(
    () => props.album.cover_url,
    () => (coverFailed.value = false)
);
</script>

<template>
    <div class="flex items-center gap-4 min-w-0">
        <div class="h-16 w-16 shrink-0 overflow-hidden rounded bg-gray-100 dark:bg-gray-700">
            <img
                v-if="!coverFailed"
                :src="album.cover_url"
                alt=""
                class="h-full w-full object-cover"
                loading="lazy"
                @error="coverFailed = true"
            />
            <div v-else class="flex h-full w-full items-center justify-center text-2xl text-gray-400" aria-hidden="true">
                ♪
            </div>
        </div>
        <div class="min-w-0 text-left">
            <p class="truncate font-medium text-gray-900 dark:text-gray-100">{{ album.title }}</p>
            <p class="truncate text-sm text-gray-600 dark:text-gray-400">{{ album.artists.join(', ') }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400">
                {{ [album.year, album.type].filter(Boolean).join(' · ') }}
            </p>
        </div>
    </div>
</template>
