<script setup lang="ts">
/**
 * DatePickerField
 *
 * A shadcn/reka-style date picker that binds a plain YYYY-MM-DD string for
 * Inertia form compatibility while also allowing direct month/year selection.
 */
import {
    getLocalTimeZone,
    parseDate,
    today,
    type DateValue,
} from '@internationalized/date';
import { CalendarIcon } from 'lucide-vue-next';
import {
    DatePickerCalendar,
    DatePickerCell,
    DatePickerCellTrigger,
    DatePickerContent,
    DatePickerField as DatePickerFieldRoot,
    DatePickerGrid,
    DatePickerGridBody,
    DatePickerGridHead,
    DatePickerGridRow,
    DatePickerHeadCell,
    DatePickerHeader,
    DatePickerInput as DatePickerSegment,
    DatePickerNext,
    DatePickerPrev,
    DatePickerRoot,
    DatePickerTrigger,
} from 'reka-ui';
import { computed, ref, watch } from 'vue';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';

const props = defineProps<{
    modelValue?: string | null;
    name?: string;
    id?: string;
    disabled?: boolean;
    class?: string;
    fromYear?: number;
    toYear?: number;
}>();

const currentYear = new Date().getFullYear();
const fromYear = computed(() => props.fromYear ?? currentYear - 120);
const toYear = computed(() => props.toYear ?? currentYear + 10);

const emit = defineEmits<{
    (e: 'update:modelValue', value: string | null): void;
}>();

const calendarValue = computed<DateValue | undefined>(() => {
    if (!props.modelValue) {
        return undefined;
    }

    try {
        return parseDate(props.modelValue);
    } catch {
        return undefined;
    }
});

const calendarPlaceholder = ref<DateValue>(
    calendarValue.value ?? today(getLocalTimeZone()),
);

watch(calendarValue, (value) => {
    if (value) {
        calendarPlaceholder.value = value;
    }
});

const monthOptions = computed(() =>
    Array.from({ length: 12 }, (_, index) => ({
        value: String(index + 1),
        label: new Intl.DateTimeFormat('en-US', { month: 'long' }).format(
            new Date(2024, index, 1),
        ),
    })),
);

const yearOptions = computed(() => {
    const years = Array.from(
        { length: toYear.value - fromYear.value + 1 },
        (_, index) => fromYear.value + index,
    );

    return years.reverse().map((year) => ({
        value: String(year),
        label: String(year),
    }));
});

const selectedMonth = computed({
    get: () => String(calendarPlaceholder.value.month),
    set: (value: string) => {
        calendarPlaceholder.value = calendarPlaceholder.value.set({
            month: Number(value),
        });
    },
});

const selectedYear = computed({
    get: () => String(calendarPlaceholder.value.year),
    set: (value: string) => {
        calendarPlaceholder.value = calendarPlaceholder.value.set({
            year: Number(value),
        });
    },
});

function onPlaceholderChange(value: DateValue) {
    calendarPlaceholder.value = value;
}

function onValueChange(value: DateValue | undefined) {
    if (!value) {
        emit('update:modelValue', null);
        return;
    }

    const month = String(value.month).padStart(2, '0');
    const day = String(value.day).padStart(2, '0');

    emit('update:modelValue', `${value.year}-${month}-${day}`);
}
</script>

<template>
    <DatePickerRoot
        :model-value="calendarValue"
        :placeholder="calendarPlaceholder"
        :disabled="disabled"
        granularity="day"
        @update:model-value="onValueChange"
        @update:placeholder="onPlaceholderChange"
    >
        <DatePickerFieldRoot
            :id="id"
            :class="
                cn(
                    'flex h-9 w-full items-center rounded-md border border-input bg-transparent px-3 py-1 text-sm shadow-xs transition-[color,box-shadow] focus-within:border-ring focus-within:ring-[3px] focus-within:ring-ring/50 data-[disabled]:pointer-events-none data-[disabled]:opacity-50 dark:bg-input/30',
                    props.class,
                )
            "
            v-slot="{ segments }"
        >
            <template
                v-for="(item, index) in segments"
                :key="`${item.part}-${index}`"
            >
                <DatePickerSegment
                    v-if="item.part === 'literal'"
                    :part="item.part"
                    class="text-muted-foreground"
                >
                    {{ item.value }}
                </DatePickerSegment>
                <DatePickerSegment
                    v-else
                    :part="item.part"
                    class="rounded px-0.5 focus:bg-accent focus:text-accent-foreground data-[placeholder]:text-muted-foreground"
                >
                    {{ item.value }}
                </DatePickerSegment>
            </template>

            <DatePickerTrigger
                class="ml-auto shrink-0 text-muted-foreground transition hover:text-foreground"
                aria-label="Open calendar"
            >
                <CalendarIcon class="size-4" />
            </DatePickerTrigger>
        </DatePickerFieldRoot>

        <DatePickerContent
            :side-offset="4"
            :class="
                cn(
                    'z-50 rounded-md border bg-popover p-0 text-popover-foreground shadow-md outline-none data-[state=closed]:animate-out data-[state=closed]:fade-out-0 data-[state=closed]:zoom-out-95 data-[state=open]:animate-in data-[state=open]:fade-in-0 data-[state=open]:zoom-in-95',
                )
            "
        >
            <DatePickerCalendar v-slot="{ weekDays, grid }">
                <DatePickerHeader class="flex items-center gap-2 px-3 py-3">
                    <DatePickerPrev
                        class="inline-flex size-8 items-center justify-center rounded-md border bg-transparent text-sm hover:bg-accent hover:text-accent-foreground disabled:pointer-events-none disabled:opacity-50"
                        aria-label="Previous month"
                    >
                        ‹
                    </DatePickerPrev>

                    <div class="grid flex-1 grid-cols-2 gap-2">
                        <Select v-model="selectedMonth">
                            <SelectTrigger class="h-8 w-full">
                                <SelectValue placeholder="Month" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem
                                    v-for="month in monthOptions"
                                    :key="month.value"
                                    :value="month.value"
                                >
                                    {{ month.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>

                        <Select v-model="selectedYear">
                            <SelectTrigger class="h-8 w-full">
                                <SelectValue placeholder="Year" />
                            </SelectTrigger>
                            <SelectContent class="max-h-60">
                                <SelectItem
                                    v-for="year in yearOptions"
                                    :key="year.value"
                                    :value="year.value"
                                >
                                    {{ year.label }}
                                </SelectItem>
                            </SelectContent>
                        </Select>
                    </div>

                    <DatePickerNext
                        class="inline-flex size-8 items-center justify-center rounded-md border bg-transparent text-sm hover:bg-accent hover:text-accent-foreground disabled:pointer-events-none disabled:opacity-50"
                        aria-label="Next month"
                    >
                        ›
                    </DatePickerNext>
                </DatePickerHeader>

                <div
                    class="flex flex-col space-y-4 px-3 pb-3 sm:flex-row sm:space-y-0 sm:space-x-4"
                >
                    <DatePickerGrid
                        v-for="month in grid"
                        :key="month.value.toString()"
                        class="w-full border-collapse space-y-1 select-none"
                    >
                        <DatePickerGridHead>
                            <DatePickerGridRow class="mb-1 flex justify-around">
                                <DatePickerHeadCell
                                    v-for="day in weekDays"
                                    :key="day"
                                    class="w-8 rounded-md text-center text-xs font-normal text-muted-foreground"
                                >
                                    {{ day }}
                                </DatePickerHeadCell>
                            </DatePickerGridRow>
                        </DatePickerGridHead>

                        <DatePickerGridBody>
                            <DatePickerGridRow
                                v-for="(weekDates, index) in month.rows"
                                :key="`week-${index}`"
                                class="flex w-full justify-around"
                            >
                                <DatePickerCell
                                    v-for="weekDate in weekDates"
                                    :key="weekDate.toString()"
                                    :date="weekDate"
                                    class="relative p-0 text-center text-sm"
                                >
                                    <DatePickerCellTrigger
                                        :day="weekDate"
                                        :month="month.value"
                                        class="relative flex size-8 items-center justify-center rounded-md p-0 text-sm font-normal outline-none hover:bg-accent hover:text-accent-foreground focus-visible:ring-2 focus-visible:ring-ring data-[disabled]:pointer-events-none data-[disabled]:opacity-30 data-[outside-month]:pointer-events-none data-[outside-month]:opacity-50 data-[selected]:bg-primary data-[selected]:text-primary-foreground data-[today]:bg-accent data-[today]:text-accent-foreground data-[selected]:data-[today]:bg-primary data-[selected]:data-[today]:text-primary-foreground"
                                    />
                                </DatePickerCell>
                            </DatePickerGridRow>
                        </DatePickerGridBody>
                    </DatePickerGrid>
                </div>
            </DatePickerCalendar>
        </DatePickerContent>

        <input
            v-if="name"
            type="hidden"
            :name="name"
            :value="modelValue ?? ''"
        />
    </DatePickerRoot>
</template>
