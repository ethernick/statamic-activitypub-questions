<template>
    <div 
        class="p-6 bg-white dark:bg-gray-850 rounded-xl ring ring-gray-200 dark:ring-x-0 dark:ring-b-0 dark:ring-gray-700/80 shadow-ui-md cursor-pointer hover:ring-2 hover:ring-blue-500/50 transition-all duration-300"
        @click="$emit('select', poll)"
    >
        <div class="font-medium text-base mb-6 prose dark:prose-invert prose-sm" v-html="poll.content || poll.title" />
        
        <div class="space-y-4">
            <div v-for="(option, idx) in poll.options" :key="idx">
                <div class="flex justify-between text-xs mb-1">
                    <span class="font-medium truncate mr-2" v-text="option.name" />
                    <span class="text-gray-500" v-text="calculatePercentage(option.count) + '%'" />
                </div>
                <div class="h-2 w-full bg-gray-100 dark:bg-gray-700 rounded-full overflow-hidden">
                    <div 
                        class="h-full bg-blue-500 transition-all duration-500" 
                        :style="{ width: calculatePercentage(option.count) + '%' }"
                    />
                </div>
            </div>
        </div>

        <div class="mt-4 pt-4 border-t border-gray-100 dark:border-gray-700 flex justify-between items-center text-xs text-gray-500">
            <span v-text="poll.voters_count + ' votes'" />
            <span v-if="poll.closed" class="px-2 py-0.5 bg-gray-100 dark:bg-gray-900 rounded-full">Closed</span>
            <span v-else class="text-green-600 font-semibold">• Active</span>
        </div>
    </div>
</template>

<script>
export default {
    props: ['poll'],
    methods: {
        calculatePercentage(count) {
            if (!this.poll.voters_count) return 0;
            return Math.round((count / this.poll.voters_count) * 100);
        }
    }
}
</script>
