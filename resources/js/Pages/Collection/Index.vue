<script setup lang="ts">
import VinylCover from '@/Components/VinylCover.vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { CollectionItem, Paginated, PaginationLink } from '@/types';
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
    items: Paginated<CollectionItem>;
}>();

// Laravel's default labels are in English and contain HTML entities
function paginationLabel(link: PaginationLink): string {
    if (link.label.includes('Previous')) {
        return '‹ Précédent';
    }
    if (link.label.includes('Next')) {
        return 'Suivant ›';
    }
    return link.label;
}
</script>

<template>
    <Head title="Ma collection" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">Ma collection</h2>
                <Link
                    :href="route('collection.create')"
                    class="inline-flex items-center px-4 py-2 bg-gray-800 dark:bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-white dark:text-gray-800 uppercase tracking-widest hover:bg-gray-700 dark:hover:bg-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150"
                >
                    Ajouter un album
                </Link>
            </div>
        </template>

        <div class="py-12">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div
                    v-if="$page.props.flash.success"
                    class="mb-6 rounded-lg bg-green-50 dark:bg-green-900/40 p-4 text-sm font-medium text-green-800 dark:text-green-200"
                    role="status"
                >
                    {{ $page.props.flash.success }}
                </div>

                <div
                    v-if="items.data.length === 0"
                    class="bg-white dark:bg-gray-800 shadow-sm sm:rounded-lg p-6 text-gray-900 dark:text-gray-100"
                >
                    Votre collection est vide pour l'instant.
                </div>

                <template v-else>
                    <ul class="grid grid-cols-2 gap-6 sm:grid-cols-3 lg:grid-cols-4">
                        <li
                            v-for="item in items.data"
                            :key="item.id"
                            class="group bg-white dark:bg-gray-800 shadow-sm rounded-lg overflow-hidden"
                        >
                            <VinylCover :cover-url="item.cover_url" :title="item.title" :seed="item.id" />

                            <!-- The sleeve: the record seems to slide out from behind it -->
                            <div
                                class="relative p-4 text-sm bg-white dark:bg-gray-800 border-t border-gray-200 dark:border-gray-600 shadow-[0_-6px_10px_-6px_rgba(0,0,0,0.45)]"
                            >
                                <p class="font-semibold text-gray-900 dark:text-gray-100 truncate" :title="item.title">
                                    {{ item.title }}
                                </p>
                                <p class="text-gray-600 dark:text-gray-400 truncate">
                                    {{ item.artists.join(', ') || 'Artiste inconnu' }}
                                </p>
                                <p class="mt-1 text-gray-500 dark:text-gray-500">
                                    <span v-if="item.year">{{ item.year }}</span>
                                    <span v-if="item.year && item.format"> · </span>
                                    <span v-if="item.format">{{ item.format }}</span>
                                </p>
                                <Link
                                    :href="route('collection.edit', item.id)"
                                    class="mt-2 inline-block text-indigo-600 dark:text-indigo-400 hover:underline"
                                >
                                    Modifier
                                </Link>
                            </div>
                        </li>
                    </ul>

                    <nav
                        v-if="items.meta.last_page > 1"
                        class="mt-8 flex flex-wrap justify-center gap-1"
                        aria-label="Pagination"
                    >
                        <template v-for="link in items.meta.links" :key="link.label">
                            <Link
                                v-if="link.url"
                                :href="link.url"
                                preserve-scroll
                                class="px-3 py-1 rounded-md text-sm"
                                :class="
                                    link.active
                                        ? 'bg-gray-800 text-white dark:bg-gray-200 dark:text-gray-800'
                                        : 'bg-white text-gray-700 hover:bg-gray-100 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700'
                                "
                            >
                                {{ paginationLabel(link) }}
                            </Link>
                            <span v-else class="px-3 py-1 rounded-md text-sm text-gray-400 dark:text-gray-600">
                                {{ paginationLabel(link) }}
                            </span>
                        </template>
                    </nav>
                </template>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
