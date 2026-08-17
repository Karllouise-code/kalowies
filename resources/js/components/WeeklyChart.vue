<template>
    <div>
        <canvas ref="canvas"></canvas>
    </div>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import Chart from 'chart.js/auto'

const props = defineProps({
    data: { type: Array, default: () => [] },
})

const canvas = ref(null)
let chart = null

function render() {
    if (!canvas.value) return
    if (chart) chart.destroy()
    chart = new Chart(canvas.value, {
        type: 'bar',
        data: {
            labels: props.data.map((d) => d.date.slice(5)),
            datasets: [
                {
                    label: 'Calories',
                    data: props.data.map((d) => d.calories),
                    backgroundColor: '#14b8a6',
                    borderRadius: 8,
                },
            ],
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true } },
        },
    })
}

onBeforeUnmount(() => {
    if (chart) {
        chart.destroy()
        chart = null
    }
})

onMounted(() => render())
watch(() => props.data, render, { deep: true })
</script>
