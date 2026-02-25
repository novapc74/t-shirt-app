<template>
    <div class="max-w-7xl mx-auto p-8">
        <h1 class="text-3xl font-black mb-10">Каталог</h1>

        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            <div v-for="category in categories" :key="category.id" class="border p-6 rounded-3xl bg-gray-50 hover:shadow-lg transition">
                <div class="flex justify-between items-center mb-4">
                    <h2 class="text-xl font-bold">{{ category.name }}</h2>
                    <span class="text-xs font-bold bg-white px-3 py-1 rounded-full border">
                        {{ category.total_variants_count }} шт.
                    </span>
                </div>

                <!-- Рекурсивный список детей -->
                <ul v-if="category.children_recursive?.length" class="space-y-2">
                    <li v-for="child in category.children_recursive" :key="child.id" class="flex justify-between text-sm text-gray-600 hover:text-indigo-600">
                        <a :href="`/catalog/${child.slug}`">{{ child.name }}</a>
                        <span class="text-gray-400">{{ child.variants_count }}</span>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</template>

<script setup>
// Ключевой момент: имя переменной должно совпадать с ключом в контроллере
defineProps({
    categories: Array
})
</script>
