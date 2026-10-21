<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{
    coverUrl: string | null;
    title: string;
    // Used to give each record without a cover its own label color
    seed: number;
}>();

const labelColor = computed(() => `hsl(${(props.seed * 47) % 360} 55% 50%)`);
</script>

<template>
    <!-- The bottom of the record is clipped: it looks half slid into its sleeve -->
    <div class="aspect-[100/80] overflow-hidden bg-gray-100 dark:bg-gray-700">
        <div
            class="vinyl relative mx-[6%] mt-[6%] aspect-square rounded-full shadow-lg transition-transform duration-500 ease-out motion-safe:group-hover:-translate-y-[5%] motion-safe:group-hover:rotate-12"
        >
            <div
                class="absolute inset-[30%] rounded-full overflow-hidden ring-2 ring-black/40"
                :style="coverUrl ? undefined : { backgroundColor: labelColor }"
            >
                <img
                    v-if="coverUrl"
                    :src="coverUrl"
                    :alt="`Pochette de ${title}`"
                    class="h-full w-full object-cover"
                    loading="lazy"
                />
            </div>
            <!-- Spindle hole -->
            <div
                class="absolute left-1/2 top-1/2 h-[4%] w-[4%] -translate-x-1/2 -translate-y-1/2 rounded-full bg-gray-100 dark:bg-gray-700"
            />
        </div>
    </div>
</template>

<style scoped>
/* Grooves, plus two light reflections across the record */
.vinyl {
    background:
        conic-gradient(
            from 20deg,
            transparent 0deg 30deg,
            rgb(255 255 255 / 0.12) 45deg,
            transparent 60deg 210deg,
            rgb(255 255 255 / 0.12) 225deg,
            transparent 240deg
        ),
        repeating-radial-gradient(circle, #111 0 2px, #222 2px 3px);
}
</style>
