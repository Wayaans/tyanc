<script setup lang="ts">
import { watch } from "vue";
import { Button } from "@/components/ui/button";
import { notify } from "@/lib/notify";
import { useTranslations } from "@/lib/translations";

const { __ } = useTranslations();

const props = withDefaults(
  defineProps<{
    processing: boolean;
    recentlySuccessful: boolean;
    label?: string;
  }>(),
  { label: "Save changes" }
);

watch(
  () => props.recentlySuccessful,
  (value, previousValue) => {
    if (!value || previousValue) {
      return;
    }

    notify.success(__("Saved."));
  }
);
</script>

<template>
  <div class="flex items-center gap-4">
    <Button type="submit" :disabled="processing">
      {{ processing ? __("Saving…") : __(label) }}
    </Button>
  </div>
</template>
