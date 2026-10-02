<template>
  <div>
    <!-- 人友好：预设频率 -->
    <a-select
      :model-value="presetKey"
      allow-create
      :placeholder="`常用频率`"
      style="width: 100%"
      @change="onPresetChange"
    >
      <a-option v-for="p in presets" :key="p.key" :value="p.key">
        {{ p.label }}
      </a-option>
      <template #label="{ data }">
        <span>{{ presets.find((p) => p.key === data?.value)?.label ?? data?.value }}</span>
      </template>
    </a-select>

    <!-- 高级模式：直接编辑表达式 -->
    <a-input-group style="margin-top: 8px">
      <a-input
        :model-value="modelValue"
        placeholder="*/5 * * * *"
        allow-clear
        @input="$emit('update:modelValue', $event)"
      />
      <a-button @click="advanced = !advanced">
        {{ advanced ? '简化' : '自定义' }}
      </a-button>
    </a-input-group>

    <!-- 可视化编辑 -->
    <div v-if="advanced" style="margin-top: 8px">
      <a-space wrap>
        <span class="unit-label">分钟</span>
        <a-select v-model="minute.mode" size="small" style="width: 130px" @change="emitCron">
          <a-option value="every">每分钟</a-option>
          <a-option value="step">间隔</a-option>
          <a-option value="at">指定</a-option>
        </a-select>
        <a-input-number
          v-if="minute.mode === 'step'"
          v-model="minute.step"
          size="small"
          :min="2"
          :max="59"
          style="width: 80px"
          @change="emitCron"
        />
        <a-input
          v-if="minute.mode === 'at'"
          v-model="minute.at"
          size="small"
          placeholder="0,15,30,45"
          style="width: 120px"
          @change="emitCron"
        />
      </a-space>
      <a-space wrap style="margin-top: 6px">
        <span class="unit-label">小时</span>
        <a-select v-model="hour.mode" size="small" style="width: 130px" @change="emitCron">
          <a-option value="every">每小时</a-option>
          <a-option value="step">间隔</a-option>
          <a-option value="at">指定</a-option>
        </a-select>
        <a-input-number
          v-if="hour.mode === 'step'"
          v-model="hour.step"
          size="small"
          :min="2"
          :max="23"
          style="width: 80px"
          @change="emitCron"
        />
        <a-input
          v-if="hour.mode === 'at'"
          v-model="hour.at"
          size="small"
          placeholder="8,20"
          style="width: 120px"
          @change="emitCron"
        />
      </a-space>
      <a-space wrap style="margin-top: 6px">
        <span class="unit-label">星期</span>
        <a-checkbox-group v-model="weekdays" size="small" @change="emitCron">
          <a-checkbox v-for="d in weekNames" :key="d.value" :value="d.value">
            {{ d.label }}
          </a-checkbox>
        </a-checkbox-group>
      </a-space>
      <div style="margin-top: 6px">
        <span class="cron-preview">{{ modelValue }}</span>
      </div>
    </div>
  </div>
</template>

<script lang="ts" setup>
  import { reactive, ref, watch } from 'vue';

  const props = defineProps<{ modelValue: string }>();
  const emit = defineEmits<{
    (e: 'update:modelValue', value: string): void;
  }>();

  const advanced = ref(false);

  const presets = [
    { key: 'every-minute', label: '每分钟', cron: '* * * * *' },
    { key: 'every-5-min', label: '每 5 分钟', cron: '*/5 * * * *' },
    { key: 'every-10-min', label: '每 10 分钟', cron: '*/10 * * * *' },
    { key: 'every-30-min', label: '每 30 分钟', cron: '*/30 * * * *' },
    { key: 'hourly', label: '每小时整点', cron: '0 * * * *' },
    { key: 'daily-8', label: '每天 8:00', cron: '0 8 * * *' },
    { key: 'daily-3', label: '每天凌晨 3:00', cron: '0 3 * * *' },
    { key: 'weekdays-9', label: '工作日 9:00', cron: '0 9 * * 1-5' },
    { key: 'weekly-mon', label: '每周一 9:00', cron: '0 9 * * 1' },
    { key: 'monthly-1', label: '每月 1 日 0:00', cron: '0 0 1 * *' },
  ];

  const presetKey = ref('');

  // 表达式 → 可视化状态（反向解析尽力而为）
  const minute = reactive({ mode: 'every', step: 5, at: '' });
  const hour = reactive({ mode: 'every', step: 1, at: '' });
  const weekdays = ref<number[]>([]);
  const weekNames = [
    { value: 0, label: '日' },
    { value: 1, label: '一' },
    { value: 2, label: '二' },
    { value: 3, label: '三' },
    { value: 4, label: '四' },
    { value: 5, label: '五' },
    { value: 6, label: '六' },
  ];

  function onPresetChange(key: string | number | boolean | Record<string, any> | (string | number | boolean | Record<string, any>)[]) {
    const preset = presets.find((p) => p.key === key);
    if (preset) {
      emit('update:modelValue', preset.cron);
      parseToVisual(preset.cron);
    }
  }

  // 由可视化状态合成 cron
  function emitCron() {
    const minutePart =
      minute.mode === 'every'
        ? '*'
        : minute.mode === 'step'
          ? `*/${minute.step}`
          : minute.at || '*';
    const hourPart =
      hour.mode === 'every'
        ? '*'
        : hour.mode === 'step'
          ? `*/${hour.step}`
          : hour.at || '*';
    const weekPart = weekdays.value.length
      ? [...weekdays.value].sort().join(',')
      : '*';
    emit('update:modelValue', `${minutePart} ${hourPart} * * ${weekPart}`);
  }

  // 尝试反向解析（用于外部值变化时同步可视化）
  function parseToVisual(cron: string) {
    const parts = cron.trim().split(/\s+/);
    if (parts.length !== 5) return;
    const [m, h, , , w] = parts;
    minute.mode = m === '*' ? 'every' : m.startsWith('*/') ? 'step' : 'at';
    if (minute.mode === 'step') minute.step = parseInt(m.slice(2), 10) || 5;
    if (minute.mode === 'at') minute.at = m;
    hour.mode = h === '*' ? 'every' : h.startsWith('*/') ? 'step' : 'at';
    if (hour.mode === 'step') hour.step = parseInt(h.slice(2), 10) || 1;
    if (hour.mode === 'at') hour.at = h;
    weekdays.value =
      w === '*' ? [] : w.split(',').map((x) => parseInt(x, 10)).filter((n) => !Number.isNaN(n));
    const hit = presets.find((p) => p.cron === cron);
    presetKey.value = hit?.key ?? '';
  }

  watch(
    () => props.modelValue,
    (val) => parseToVisual(val),
    { immediate: true }
  );
</script>

<style scoped>
  .unit-label {
    display: inline-block;
    width: 34px;
    font-size: 13px;
    color: var(--color-text-2);
  }

  .cron-preview {
    display: inline-block;
    padding: 2px 10px;
    font-family: monospace;
    font-size: 13px;
    color: var(--color-text-1);
    background: var(--color-fill-2);
    border-radius: 4px;
  }
</style>
