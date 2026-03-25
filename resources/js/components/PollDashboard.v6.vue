<template>
    <div class="max-w-5xl mx-auto">
        <header class="flex flex-wrap items-center justify-between gap-4 px-2 sm:px-0 py-6 max-md:pb-8 md:py-8">
            <h1 class="text-[25px] leading-[1.25] st-text-legibility font-medium antialiased flex items-center gap-2.5 md:flex-1" v-text="'Poll Analytics'" />
            
            <div v-if="!loading" class="flex items-center gap-4">
                <div class="flex rounded-lg p-1 gap-1 border ap-filter-container items-center shadow-sm">
                    <button 
                        v-for="f in ['all', 'internal', 'external']" 
                        :key="f"
                        @click="filter = f"
                        class="px-4 py-1.5 rounded-md text-sm font-medium transition-all capitalize ap-filter-btn"
                        :class="filter === f ? 'active' : 'inactive'"
                    >
                        {{ f }}
                    </button>
                </div>
                <div class="text-xs text-gray-500 font-medium bg-gray-100 dark:bg-gray-900 px-3 py-1.5 rounded-full border border-gray-200 dark:border-gray-800 hidden sm:block">
                    {{ filteredPolls.length }} <span class="text-gray-400 font-normal">of</span> {{ metrics.polls.length }}
                </div>
            </div>
        </header>

        <div v-if="loading" class="flex justify-center py-12">
            <loading-graphic />
        </div>

        <div v-else class="space-y-8">

            <!-- Trend Chart Panel -->
            <div class="@container/panel relative bg-gray-150 dark:bg-gray-950/35 dark:inset-shadow-2xs dark:inset-shadow-black w-full rounded-2xl max-[600px]:p-1.25 p-1.75 [&:has(>[data-ui-panel-header])]:pt-0 focus-none starting-style-transition">
                <header data-ui-panel-header class="px-4.5 py-3 flex items-center justify-between">
                    <h2 class="font-bold text-gray-900 dark:text-gray-100">Vote Trends</h2>
                </header>
                <div class="bg-white dark:bg-gray-850 rounded-xl ring ring-gray-200 dark:ring-x-0 dark:ring-b-0 dark:ring-gray-700/80 shadow-ui-md px-4 sm:px-4.5 py-5">
                    <div class="h-64">
                        <poll-trend-chart 
                            :labels="metrics.chart.labels" 
                            :datasets="metrics.chart.datasets" 
                        />
                    </div>
                </div>
            </div>

            <!-- Rollup Metrics Panel -->
            <div class="@container/panel relative bg-gray-150 dark:bg-gray-950/35 dark:inset-shadow-2xs dark:inset-shadow-black w-full rounded-2xl max-[600px]:p-1.25 p-1.75 focus-none starting-style-transition">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="@container/panel bg-white dark:bg-gray-850 rounded-xl ring ring-gray-200 dark:ring-x-0 dark:ring-b-0 dark:ring-gray-700/80 shadow-ui-md p-6 flex items-center gap-4 py-2 md:py-0 md:px-4">
                        <div class="p-3 bg-blue-50 dark:bg-blue-900/20 rounded-full">
                            <statamic-icon name="charts" class="w-6 h-6 text-blue-600" />
                        </div>
                        <div>
                            <div class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-1">Total Polls</div>
                            <div class="text-2xl font-bold text-gray-900 dark:text-white" v-text="filteredRollup.total_polls" />
                        </div>
                    </div>
                    <div class="@container/panel bg-white dark:bg-gray-850 rounded-xl ring ring-gray-200 dark:ring-x-0 dark:ring-b-0 dark:ring-gray-700/80 shadow-ui-md p-6 flex items-center gap-4 py-2 md:py-0 md:px-4">
                        <div class="p-3 bg-green-50 dark:bg-green-900/20 rounded-full">
                            <statamic-icon name="check" class="w-6 h-6 text-green-600" />
                        </div>
                        <div>
                            <div class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-1">Active Polls</div>
                            <div class="text-2xl font-bold text-gray-900 dark:text-white" v-text="filteredRollup.active_polls" />
                        </div>
                    </div>
                    <div class="@container/panel bg-white dark:bg-gray-850 rounded-xl ring ring-gray-200 dark:ring-x-0 dark:ring-b-0 dark:ring-gray-700/80 shadow-ui-md p-6 flex items-center gap-4 py-2 md:py-0 md:px-4">
                        <div class="p-3 bg-purple-50 dark:bg-purple-900/20 rounded-full">
                            <statamic-icon name="users" class="w-6 h-6 text-purple-600" />
                        </div>
                        <div>
                            <div class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-1">Total Votes</div>
                            <div class="text-2xl font-bold text-gray-900 dark:text-white" v-text="filteredRollup.total_votes" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Poll Grid Panel -->
            <div class="@container/panel relative bg-gray-150 dark:bg-gray-950/35 dark:inset-shadow-2xs dark:inset-shadow-black w-full rounded-2xl max-[600px]:p-1.25 p-1.75 [&:has(>[data-ui-panel-header])]:pt-0 focus-none starting-style-transition">
                <header data-ui-panel-header class="px-4.5 py-3 flex items-center justify-between">
                    <h2 class="font-bold text-gray-900 dark:text-gray-100 capitalize">{{ filter }} Polls</h2>
                </header>
                <div class="px-4 sm:px-4.5 space-y-2 pb-2">
                    <div v-if="filteredPolls.length" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <poll-card 
                            v-for="poll in filteredPolls" 
                            :key="poll.id" 
                            :poll="poll" 
                            @select="openPoll(poll)"
                            class="!border-gray-100 dark:!border-gray-800 !shadow-none ring-1 ring-gray-950/5 dark:ring-white/10"
                        />
                    </div>
                    <div v-else class="text-center py-12 bg-white dark:bg-gray-850 rounded-xl ring-1 ring-gray-200 dark:ring-gray-800 text-gray-500 italic">
                        No {{ filter }} polls found.
                    </div>
                </div>
            </div>
        </div>

        <poll-drawer 
            ref="drawer" 
            :poll="selectedPoll" 
            :actors="actors"
            :permissions="permissions"
            :metrics-url="metricsUrl"
            :voters-url="votersUrl"
            :close-url="closeUrl"
            @closed="selectedPoll = null" 
            @refresh="fetchMetrics"
        />
    </div>
</template>

<script>
import PollCard from './PollCard.vue';
import PollDrawer from './PollDrawer.vue';
import PollTrendChart from './PollTrendChart.vue';

export default {
    components: {
        PollCard,
        PollDrawer,
        PollTrendChart
    },
    props: {
        metricsUrl: String,
        votersUrl: String,
        closeUrl: String
    },
    data() {
        return {
            loading: true,
            metrics: null,
            selectedPoll: null,
            filter: 'all',
            actors: [],
            permissions: { manage_polls: false }
        }
    },
    computed: {
        filteredPolls() {
            if (!this.metrics) return [];
            
            if (this.filter === 'internal') {
                return this.metrics.polls.filter(p => p.is_internal);
            }
            
            if (this.filter === 'external') {
                return this.metrics.polls.filter(p => !p.is_internal);
            }
            
            return this.metrics.polls;
        },
        filteredRollup() {
            if (!this.metrics) return { total_polls: 0, active_polls: 0, total_votes: 0 };
            
            return {
                total_polls: this.filteredPolls.length,
                active_polls: this.filteredPolls.filter(p => !p.closed).length,
                total_votes: this.filteredPolls.reduce((sum, p) => sum + (p.voters_count || 0), 0)
            };
        }
    },
    mounted() {
        this.fetchMetrics();
        
        if (typeof Statamic !== 'undefined' && Statamic.$activitypub) {
            Statamic.$activitypub.bus.on('activitypub-open-poll-drawer', (note) => {
                // Find matching poll in our list or fetch specifically
                this.selectedPoll = note; // We'll handle both formats in the drawer
                this.$refs.drawer.open();
            });
        }
    },
    methods: {
        fetchMetrics() {
            this.loading = true;
            this.$axios.get(this.metricsUrl)
                .then(response => {
                    this.metrics = response.data;
                    this.actors = response.data.actors || [];
                    this.permissions = response.data.permissions || { manage_polls: false };
                    this.loading = false;
                });
        },
        openPoll(poll) {
            this.selectedPoll = poll;
            this.$refs.drawer.open();
        }
    }
}
</script>

<style>
.ap-filter-container {
    background-color: #e5e7eb; /* gray-200 */
    border-color: #e5e7eb;
}
html.dark .ap-filter-container,
html.is-dark .ap-filter-container,
html.isdark .ap-filter-container {
    background-color: #171717; /* neutral-900 */
    border-color: #262626; /* neutral-800 */
}

/* Light Mode Defaults */
.ap-filter-btn.active {
    background-color: white;
    color: #111827; /* gray-900 */
    box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
}
.ap-filter-btn.inactive {
    color: #6b7280; /* gray-500 */
    font-weight: normal;
}
.ap-filter-btn.inactive:hover {
    color: #374151; /* gray-700 */
}

/* Dark Mode Overrides */
html.dark .ap-filter-btn.active,
html.is-dark .ap-filter-btn.active,
html.isdark .ap-filter-btn.active {
    background-color: #404040; /* neutral-700 */
    color: #f5f5f5; /* neutral-100 */
}
html.dark .ap-filter-btn.inactive,
html.is-dark .ap-filter-btn.inactive,
html.isdark .ap-filter-btn.inactive {
    color: #a3a3a3; /* neutral-400 */
}
html.dark .ap-filter-btn.inactive:hover,
html.is-dark .ap-filter-btn.inactive:hover,
html.isdark .ap-filter-btn.inactive:hover {
    color: #e5e5e5; /* neutral-200 */
}
</style>
