<template>
    <div class="relative w-full h-full min-h-[150px]">
        <svg v-if="hasData" class="w-full h-full overflow-visible" :viewBox="`0 0 ${width} ${height}`" preserveAspectRatio="none">
            <!-- Grid Lines (Horizontal) -->
            <line v-for="i in 4" :key="'grid-'+i" 
                x1="0" :y1="(height/4)*i" :x2="width" :y2="(height/4)*i" 
                class="stroke-gray-100 dark:stroke-gray-800" stroke-width="1" 
            />

            <!-- Dataset Lines -->
            <g v-for="(dataset, dsIdx) in datasets" :key="'ds-'+dsIdx">
                <path 
                    :d="getPath(dataset.data)" 
                    fill="none" 
                    :stroke="getColor(dsIdx)" 
                    stroke-width="2" 
                    stroke-linecap="round" 
                    stroke-linejoin="round"
                />
            </g>

            <!-- Points for single dataset -->
            <g v-if="datasets.length === 1">
                <circle 
                    v-for="(point, pIdx) in datasets[0].data" 
                    :key="'p-'+pIdx"
                    :cx="(width / (datasets[0].data.length - 1)) * pIdx"
                    :cy="height - (point / maxVal) * height"
                    r="4"
                    :fill="getColor(0)"
                    class="stroke-white dark:stroke-gray-900"
                    stroke-width="2"
                />
            </g>
        </svg>
        
        <div v-else class="flex items-center justify-center h-full text-gray-400 italic text-sm">
            Not enough data to display trend.
        </div>

        <!-- Tooltip Placeholder or Labels -->
        <div v-if="hasData" class="absolute bottom-0 left-0 right-0 flex justify-between text-[10px] text-gray-400 mt-2 px-1">
            <span v-text="labels[0]" />
            <span v-text="labels[labels.length - 1]" />
        </div>
    </div>
</template>

<script>
export default {
    props: {
        labels: { type: Array, default: () => [] },
        datasets: { type: Array, default: () => [] },
        height: { type: Number, default: 200 },
        width: { type: Number, default: 800 }
    },
    computed: {
        hasData() {
            return this.datasets.length > 0 && this.datasets[0].data.length > 1;
        },
        maxVal() {
            if (!this.hasData) return 1;
            let max = 0;
            this.datasets.forEach(ds => {
                ds.data.forEach(v => { if (v > max) max = v; });
            });
            return max || 1;
        }
    },
    methods: {
        getPath(data) {
            const stepX = this.width / (data.length - 1);
            return data.map((val, i) => {
                const x = i * stepX;
                const y = this.height - (val / this.maxVal) * this.height;
                return (i === 0 ? 'M' : 'L') + `${x},${y}`;
            }).join(' ');
        },
        getAreaPath(data) {
            const path = this.getPath(data);
            return `${path} L${this.width},${this.height} L0,${this.height} Z`;
        },
        getColor(index) {
            const colors = [
                '#3b82f6', // blue
                '#10b981', // emerald
                '#8b5cf6', // violet
                '#f59e0b', // amber
                '#ef4444', // red
                '#06b6d4', // cyan
            ];
            return colors[index % colors.length];
        }
    }
}
</script>
