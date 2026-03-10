<script setup>
import {Link} from '@inertiajs/vue3';
import {computed, ref, onMounted, watch} from 'vue';

const props = defineProps({product: Object});

const selectedColor = ref(null);
const selectedSize = ref(null);
const selectedGender = ref(null);
const isReset = ref(false);

/**
 * 1. Умная инициализация: учитывает фильтры из URL
 */
const initDefaultSelection = () => {
    if (!props.product.variants?.length) return;

    const urlParams = new URLSearchParams(window.location.search);

    // Хелпер для извлечения ID из параметров типа filters[color][] или filters[color]
    const getParamIds = (name) => {
        const ids = [];
        for (const [key, value] of urlParams.entries()) {
            if (key.includes(`filters[${name}]`)) ids.push(String(value));
        }
        return ids;
    };

    const urlColors = getParamIds('color');
    const urlSizes = getParamIds('size');
    const urlGenders = getParamIds('gender');

    // Ищем вариант, максимально подходящий под URL
    let target = props.product.variants.find(v => {
        const mC = urlColors.length ? urlColors.includes(String(v.color.id)) : true;
        const mS = urlSizes.length ? urlSizes.includes(String(v.size.id)) : true;
        const mG = urlGenders.length ? urlGenders.includes(String(v.gender.id)) : true;
        return mC && mS && mG;
    });

    // Если точного совпадения нет, ищем хотя бы по цвету из URL
    if (!target && urlColors.length) {
        target = props.product.variants.find(v => urlColors.includes(String(v.color.id)));
    }

    // Если всё равно пусто — берем самый первый вариант
    const final = target || props.product.variants[0];

    selectedColor.value = final.color.id;
    selectedSize.value = final.size.id;
    selectedGender.value = final.gender.id;
    isReset.value = false;
};

onMounted(initDefaultSelection);

// Следим за обновлением товара (когда Inertia подгружает новые данные после фильтрации)
watch(() => props.product, initDefaultSelection, {deep: true});

/**
 * 2. Авто-дополнение: если осталось выбрать 1 параметр и он единственный
 */
watch([selectedColor, selectedSize, selectedGender], ([color, size, gender]) => {
    if (isReset.value) return;
    const selectedCount = [color, size, gender].filter(Boolean).length;
    if (selectedCount === 2) {
        const available = props.product.variants.filter(v =>
            (!color || v.color.id === color) &&
            (!size || v.size.id === size) &&
            (!gender || v.gender.id === gender)
        );
        if (available.length === 1) {
            const v = available[0];
            if (!color) selectedColor.value = v.color.id;
            if (!size) selectedSize.value = v.size.id;
            if (!gender) selectedGender.value = v.gender.id;
        }
    }
});

const resetAll = () => {
    isReset.value = true;
    selectedColor.value = null;
    selectedSize.value = null;
    selectedGender.value = null;
};

const isFullSelection = computed(() => selectedColor.value && selectedSize.value && selectedGender.value);

const currentVariant = computed(() => {
    if (!isFullSelection.value || isReset.value) return null;
    return props.product.variants.find(v =>
        v.color.id === selectedColor.value &&
        v.size.id === selectedSize.value &&
        v.gender.id === selectedGender.value
    );
});

const displayPrice = computed(() => {
    if (!currentVariant.value) return null;
    return Math.round(parseFloat(currentVariant.value.price)).toLocaleString('ru-RU');
});

const isAvailable = (type, id) => {
    return props.product.variants.some(v => {
        const mC = (type === 'color') ? v.color.id === id : (!selectedColor.value || v.color.id === selectedColor.value);
        const mS = (type === 'size') ? v.size.id === id : (!selectedSize.value || v.size.id === selectedSize.value);
        const mG = (type === 'gender') ? v.gender.id === id : (!selectedGender.value || v.gender.id === selectedGender.value);
        return mC && mS && mG;
    });
};

const getUnique = (key) => {
    const items = props.product.variants.map(v => v[key]);
    return Array.from(new Map(items.map(i => [i.id, i])).values()).sort((a, b) => a.priority - b.priority);
};

const uniqueColors = computed(() => getUnique('color'));
const uniqueSizes = computed(() => getUnique('size'));
const uniqueGenders = computed(() => getUnique('gender'));
</script>

<template>
    <div
        class="group bg-white border border-slate-50 rounded-[2.5rem] p-5 hover:shadow-[0_32px_48px_-12px_rgba(0,0,0,0.06)] transition-all duration-500 flex flex-col h-full relative overflow-hidden">

        <div
            class="aspect-square bg-slate-50 rounded-[1.8rem] mb-5 flex items-center justify-center relative overflow-hidden shrink-0 group-hover:scale-[0.98] transition-transform duration-700">
            <span class="text-slate-200 font-black text-3xl uppercase tracking-tighter select-none opacity-40">{{
                    product.brand
                }}</span>
        </div>

        <div class="flex-1 flex flex-col px-1">
            <div class="flex justify-between items-center mb-3">
                <span class="text-[8px] font-black text-indigo-400 uppercase tracking-[0.3em]">{{
                        product.brand
                    }}</span>
                <span class="text-[8px] text-slate-300 font-bold uppercase tracking-widest">{{
                        product.category
                    }}</span>
            </div>

            <h3 class="text-sm font-semibold text-slate-800 leading-tight mb-6 group-hover:text-indigo-500 transition-colors line-clamp-2">
                <Link :href="`/product/${product.slug}`">{{ product.name }}</Link>
            </h3>

            <div class="space-y-6 flex-1">
                <!-- Группа 1: Цвета (Всегда яркие) -->
                <div class="flex flex-wrap gap-3.5 items-center">
                    <button v-for="color in uniqueColors" :key="color.id"
                            @click="selectedColor = (selectedColor === color.id ? null : color.id); isReset = false"
                            :title="color.title"
                            :class="[
                            'relative w-4 h-4 rounded-full transition-all duration-500 ring-offset-2',
                            selectedColor === color.id ? 'ring-2 ring-indigo-500 scale-125 z-10' : 'ring-1 ring-slate-100 shadow-sm',
                            isAvailable('color', color.id) ? 'hover:ring-slate-300 active:scale-90' : 'cursor-default pointer-events-none'
                        ]"
                            :style="{ backgroundColor: color.hex_code || '#ccc' }"
                    ></button>
                </div>
                <!-- Группа 2: Размеры -->
                <div class="flex flex-wrap gap-1.5">
                    <button v-for="size in uniqueSizes" :key="size.id"
                            @click="selectedSize = (selectedSize === size.id ? null : size.id); isReset = false"
                            :class="[
            'min-w-[34px] text-[9px] py-1.5 px-2 rounded-lg border transition-all duration-500 uppercase tracking-tighter font-bold',
            /* Состояние: Выбран */
            selectedSize === size.id
                ? 'border-slate-500 text-slate-800 bg-slate-50'
                : 'bg-white border-slate-200 text-slate-500 hover:border-slate-400',
            /* Если недоступен: блокируем клик, но сохраняем яркость */
            !isAvailable('size', size.id) ? 'cursor-default pointer-events-none' : 'opacity-100'
        ]"
                    >
        <span class="relative inline-flex items-center justify-center">
            {{ size.title }}
            <!-- Контрастная линия поверх яркого текста -->
            <div v-if="!isAvailable('size', size.id)"
                 class="absolute h-[1px] bg-slate-400 w-[calc(100%+4px)] left-1/2 -translate-x-1/2"
            ></div>
        </span>
                    </button>
                </div>

                <!-- Группа 3: Гендер -->
                <div class="flex flex-wrap gap-1.5">
                    <button v-for="gender in uniqueGenders" :key="gender.id"
                            @click="selectedGender = (selectedGender === gender.id ? null : gender.id); isReset = false"
                            :class="[
            'text-[9px] py-1.5 px-3 rounded-lg border transition-all duration-500 uppercase tracking-tight font-bold',
            /* Состояние: Выбран */
            selectedGender === gender.id
                ? 'border-slate-500 text-slate-800 bg-slate-50'
                : 'bg-white border-slate-200 text-slate-500 hover:border-slate-400',
            /* Если недоступен: блокируем клик, но сохраняем яркость */
            !isAvailable('gender', gender.id) ? 'cursor-default pointer-events-none' : 'opacity-100'
        ]"
                    >
        <span class="relative inline-flex items-center justify-center">
            {{ gender.title }}
            <!-- Контрастная линия поверх яркого текста -->
            <div v-if="!isAvailable('gender', gender.id)"
                 class="absolute h-[1px] bg-slate-400 w-[calc(100%+6px)] left-1/2 -translate-x-1/2"
            ></div>
        </span>
                    </button>
                </div>

            </div>

            <div class="pt-5 mt-6 border-t border-slate-50 flex justify-between items-center h-12">
                <button v-if="selectedColor || selectedSize || selectedGender" @click="resetAll"
                        class="text-[8px] font-bold text-slate-300 uppercase hover:text-rose-400 transition-colors">
                    сброс
                </button>
                <div class="text-right ml-auto">
                    <div v-if="isFullSelection && displayPrice && !isReset"
                         class="animate-in fade-in slide-in-from-bottom-1 duration-700">
                        <span class="text-xl font-black text-slate-900 tracking-tighter whitespace-nowrap">
                            {{ displayPrice }}<span
                            class="text-[10px] ml-0.5 font-bold text-slate-300 italic font-serif">₽</span>
                        </span>
                    </div>
                    <div v-else class="opacity-30">
                        <span class="text-[8px] font-bold text-slate-400 uppercase tracking-[0.2em]">выбор</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.line-clamp-2 {
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
</style>
