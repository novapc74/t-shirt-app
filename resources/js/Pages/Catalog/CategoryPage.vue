<script setup>
import { Head, router } from '@inertiajs/vue3';
import { ref } from 'vue';
import FiltersSidebar from '@/Components/FiltersSidebar.vue';
import ProductCard from '@/Components/ProductCard.vue';

const props = defineProps({
    category: String,
    products: Object,
    filters: Array
});

const isProcessing = ref(false);
router.on('start', () => (isProcessing.value = true));
router.on('finish', () => (isProcessing.value = false));
</script>

<template>
    <Head :title="category" />

    <div class="max-w-[1400px] mx-auto px-4 py-10">
        <div class="flex gap-10">
            <FiltersSidebar :filters="filters" />

            <main class="flex-1">
                <div class="flex items-center justify-between mb-10">
                    <h1 class="text-4xl font-black italic uppercase tracking-tighter text-gray-900">
                        {{ category }}
                    </h1>
                    <div class="bg-gray-100 px-3 py-1 rounded-full text-[10px] font-bold text-gray-500 uppercase">
                        {{ products.data.length }} Items
                    </div>
                </div>

                <div
                    :class="['grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 transition-opacity duration-300',
                  { 'opacity-30 pointer-events-none': isProcessing }]"
                >
                    <ProductCard
                        v-for="product in products.data"
                        :key="product.id"
                        :product="product"
                    />
                </div>

                <!-- Если товаров нет -->
                <div v-if="!products.data.length" class="py-20 text-center border-2 border-dashed rounded-3xl">
                    <p class="text-gray-400 font-bold uppercase tracking-widest text-sm">Ничего не найдено</p>
                </div>
            </main>
        </div>
    </div>
</template>
