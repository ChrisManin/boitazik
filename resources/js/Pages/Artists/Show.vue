<script setup lang="ts">
import VinylCover from '@/Components/VinylCover.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { ArtistDetail, DiscographyGroup } from '@/types';
import { Head } from '@inertiajs/vue3';
import { computed } from 'vue';

const props = defineProps<{
    artist: ArtistDetail;
    importing: boolean;
    importFailed: boolean;
    discography: DiscographyGroup[];
}>();

const regionNames = new Intl.DisplayNames('fr', { type: 'region' });

// "IS" → "Islande"; an unknown code is shown as is
const countryName = computed(() => {
    if (!props.artist.country) {
        return null;
    }
    try {
        return regionNames.of(props.artist.country) ?? props.artist.country;
    } catch {
        return props.artist.country;
    }
});
</script>

<template>
    <Head :title="artist.name" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <img
                    v-if="artist.image_url"
                    :src="artist.image_url"
                    :alt="`Photo de ${artist.name}`"
                    class="h-16 w-16 rounded-full object-cover"
                />
                <div>
                    <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
                        {{ artist.name }}
                    </h2>
                    <p v-if="countryName" class="text-sm text-gray-600 dark:text-gray-400">{{ countryName }}</p>
                </div>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-10">
                <div
                    v-if="importing"
                    class="rounded-lg bg-blue-50 dark:bg-blue-900/40 p-4 text-sm font-medium text-blue-800 dark:text-blue-200"
                    role="status"
                >
                    Discographie en cours de récupération. Revenez dans quelques instants.
                </div>

                <div
                    v-else-if="importFailed"
                    class="rounded-lg bg-red-50 dark:bg-red-900/40 p-4 text-sm font-medium text-red-800 dark:text-red-200"
                    role="alert"
                >
                    La récupération de la discographie a échoué. Seuls les albums déjà connus sont affichés.
                </div>

                <div
                    v-else-if="discography.length === 0"
                    class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 text-gray-900 dark:text-gray-100"
                >
                    Aucun album connu pour cet artiste.
                </div>

                <section v-for="group in discography" :key="group.type">
                    <h3 class="mb-4 text-lg font-semibold text-gray-800 dark:text-gray-200">
                        {{ group.label }}
                        <span class="text-sm font-normal text-gray-500">({{ group.albums.length }})</span>
                    </h3>

                    <ul class="grid grid-cols-2 gap-6 sm:grid-cols-3 lg:grid-cols-4">
                        <li
                            v-for="album in group.albums"
                            :key="album.id"
                            class="group bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden"
                            :class="{ 'ring-2 ring-indigo-500': album.owned }"
                        >
                            <VinylCover :cover-url="album.cover_url" :title="album.title" :seed="album.id" />

                            <div
                                class="relative p-4 text-sm bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-600 shadow-[0_-6px_10px_-6px_rgba(0,0,0,0.45)]"
                            >
                                <p class="font-semibold text-gray-900 dark:text-gray-100 truncate" :title="album.title">
                                    {{ album.title }}
                                </p>
                                <p class="text-gray-500">{{ album.year ?? 'Année inconnue' }}</p>
                                <span
                                    v-if="album.owned"
                                    class="mt-2 inline-block rounded-full bg-indigo-100 dark:bg-indigo-900/60 px-2 py-0.5 text-xs font-medium text-indigo-800 dark:text-indigo-200"
                                >
                                    Dans ma collection
                                </span>
                            </div>
                        </li>
                    </ul>
                </section>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
