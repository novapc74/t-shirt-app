<template>
    <div class="flex flex-col border p-5 rounded-2xl shadow-sm hover:shadow-xl transition-all bg-white group h-full">
        <!-- Заголовок и Цена -->
        <div class="mb-4">
            <h4 class="font-bold text-lg text-gray-900 group-hover:text-indigo-600 transition-colors leading-tight">
                {{ product.name }}
            </h4>
            <p class="text-xl font-black text-gray-900 mt-1">
                <!-- Если конкретный вариант еще не выбран — показываем минимальную цену "от" -->
                <span v-if="!currentVariant" class="text-sm font-normal text-gray-400 mr-1">от</span>
                {{ displayPrice }} ₽
            </p>
        </div>

        <!-- Сетки кнопок (Цвет, Размер, Гендер) -->
        <div class="flex-1 space-y-5 mb-4">
            <div v-for="(options, groupName) in groupedSpecs" :key="groupName" class="flex flex-col gap-2">
                <div class="flex justify-between items-center">
                    <span class="text-[10px] font-bold text-gray-400 uppercase tracking-widest">{{ translateGroup(groupName) }}</span>
                    <!-- Кнопка сброса только для этой группы -->
                    <button
                        v-if="selection[groupName] && !isSideFilterActive"
                        @click="resetGroup(groupName)"
                        class="text-[9px] font-bold text-red-400 hover:text-red-600 uppercase"
                    >
                        сброс
                    </button>
                </div>

                <div class="flex flex-wrap gap-1.5 items-center">
                    <button
                        v-for="opt in options"
                        :key="opt.id"
                        @click="selectOption(groupName, opt.id)"
                        :disabled="!isOptionPossible(groupName, opt.id)"
                        :class="[
                            'px-3 py-1.5 rounded-md text-[11px] font-medium transition-all border relative',
                            // Активная кнопка (выбрана)
                            selection[groupName] === opt.id
                                ? 'bg-indigo-50 border-indigo-600 text-indigo-700 shadow-sm'
                                : 'bg-white border-gray-100 text-gray-700 hover:border-gray-300',
                            // Недоступная кнопка (нет такой комбинации со стоком > 0)
                            !isOptionPossible(groupName, opt.id) ? 'opacity-20 grayscale cursor-not-allowed border-dashed' : 'opacity-100'
                        ]"
                    >
                        {{ opt.value }}
                    </button>
                </div>
            </div>

            <!-- Общий сброс, если выбрано больше одной группы -->
            <button
                v-if="Object.keys(selection).length > 1 && !isSideFilterActive"
                @click="resetAll"
                class="w-full py-2 text-[9px] font-black uppercase tracking-widest text-gray-400 border border-dashed border-gray-200 rounded-lg hover:bg-gray-50 transition-all"
            >
                Сбросить все параметры
            </button>
        </div>

        <!-- Статус наличия и SKU -->
        <div class="mb-4 h-5 flex items-center justify-between">
            <div v-if="currentVariant" class="flex items-center gap-2">
                <span :class="['w-2 h-2 rounded-full', currentVariant.stock > 0 ? 'bg-green-500 animate-pulse' : 'bg-red-500']"></span>
                <span class="text-[10px] text-gray-500 font-bold uppercase tracking-tight">
                    {{ currentVariant.stock > 0 ? `В наличии: ${currentVariant.stock} шт.` : 'Нет на складе' }}
                </span>
            </div>
            <span v-if="currentVariant" class="text-[9px] text-gray-300 font-mono">{{ currentVariant.sku }}</span>
            <span v-else-if="Object.keys(selection).length > 0" class="text-[10px] text-orange-400 font-bold uppercase">Выберите параметры</span>
        </div>

        <!-- Кнопка купить -->
        <button
            @click="handleAddToCart"
            :disabled="!currentVariant || currentVariant.stock <= 0"
            class="w-full bg-indigo-600 text-white text-center py-4 rounded-xl font-black uppercase text-xs tracking-widest hover:bg-indigo-700 active:scale-95 transition-all disabled:bg-gray-100 disabled:text-gray-400 shadow-lg shadow-indigo-100 disabled:shadow-none"
        >
            {{ currentVariant ? (currentVariant.stock > 0 ? 'В корзину' : 'Нет в наличии') : 'Выберите опции' }}
        </button>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'

const props = defineProps({
    product: Object,          // Данные из ProductResource
    activeFilters: Object,    // Выбранные фильтры в сайдбаре
    isSideFilterActive: Boolean // Есть ли активные фильтры в сайдбаре
})

const selection = ref({})

// 1. Собираем уникальные опции из всех доступных вариантов
const groupedSpecs = computed(() => {
    const specs = { color: [], size: [], gender: [] };

    props.product.variants.forEach(v => {
        if (v.attributes.color) specs.color.push({ id: v.attributes.color, value: v.attribute_names.color });
        if (v.attributes.size) specs.size.push({ id: v.attributes.size, value: v.attribute_names.size });
        if (v.attributes.gender) specs.gender.push({ id: v.attributes.gender, value: v.attribute_names.gender });
    });

    return Object.fromEntries(
        Object.entries(specs).map(([key, values]) => {
            // Уникальность по ID
            const unique = Array.from(new Map(values.map(item => [item.id, item])).values());
            return [key, unique];
        }).filter(([_, values]) => values.length > 0)
    );
});

// 2. Проверка: существует ли хоть один вариант с таким набором ID и stock > 0
const isOptionPossible = (groupName, valueId) => {
    const potential = { ...selection.value, [groupName]: Number(valueId) };

    return props.product.variants.some(v => {
        const matches = Object.entries(potential).every(([g, id]) => Number(v.attributes[g]) === Number(id));
        return matches && v.stock > 0;
    });
}

// 3. Автовыбор (если осталась одна опция в группе)
const autoSelect = () => {
    let changed = false;
    Object.entries(groupedSpecs.value).forEach(([groupName, options]) => {
        if (!selection.value[groupName]) {
            const possible = options.filter(opt => isOptionPossible(groupName, opt.id));
            if (possible.length === 1) {
                selection.value[groupName] = possible[0].id;
                changed = true;
            }
        }
    });
    if (changed) autoSelect();
}

// 4. Синхронизация с фильтрами в сайдбаре
const syncWithSidebar = () => {
    const newSelection = {};
    Object.entries(groupedSpecs.value).forEach(([groupName, options]) => {
        // Мы ожидаем, что в activeFilters ключи совпадают: color, size, gender
        const sidebarIds = props.activeFilters ? props.activeFilters[groupName] : null;
        if (sidebarIds && Array.isArray(sidebarIds) && sidebarIds.length > 0) {
            const match = options.find(o => sidebarIds.includes(Number(o.id)));
            if (match && isOptionPossible(groupName, match.id)) {
                newSelection[groupName] = Number(match.id);
            }
        }
    });
    selection.value = newSelection;
    autoSelect();
}

// 5. Действия
function selectOption(group, id) {
    const valId = Number(id);
    if (selection.value[group] === valId) {
        if (!props.isSideFilterActive) delete selection.value[group];
    } else {
        selection.value[group] = valId;
        // Сбрасываем несовместимые ранее выбранные опции
        Object.keys(selection.value).forEach(g => {
            if (g !== group && !isOptionPossible(g, selection.value[g])) {
                delete selection.value[g];
            }
        });
    }
    autoSelect();
}

function resetGroup(groupName) {
    delete selection.value[groupName];
    autoSelect();
}

function resetAll() {
    selection.value = {};
    autoSelect();
}

// Перевод заголовков групп
function translateGroup(key) {
    const map = { color: 'Цвет', size: 'Размер', gender: 'Кому' };
    return map[key] || key;
}

watch(() => props.activeFilters, syncWithSidebar, { deep: true });
watch(() => props.product, syncWithSidebar, { deep: true, immediate: true });
onMounted(syncWithSidebar);

// 6. Определение текущего выбранного варианта (SKU)
const currentVariant = computed(() => {
    const groups = Object.keys(groupedSpecs.value);
    const selected = Object.keys(selection.value);

    if (groups.length > 0 && selected.length === groups.length) {
        return props.product.variants.find(v =>
            groups.every(g => Number(v.attributes[g]) === Number(selection.value[g]))
        );
    }
    return null;
});

// 7. Вычисление цены
const displayPrice = computed(() => {
    if (currentVariant.value) return currentVariant.value.price;
    // Если вариант не выбран, показываем минимальную цену (из пропса или вычисляем)
    if (props.product.min_price) return props.product.min_price;
    const allPrices = props.product.variants.map(v => v.price).filter(p => p > 0);
    return allPrices.length ? Math.min(...allPrices) : 0;
});

function handleAddToCart() {
    if (currentVariant.value) {
        alert(`🛒 Товар ${props.product.name} (SKU: ${currentVariant.value.sku}) добавлен в корзину!`);
    }
}
</script>
