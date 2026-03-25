<template>
    <div>
        <header class="flex items-center justify-between mb-6">
            <h1 v-text="'Poll Analytics'" />

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
                <div class="text-xs text-gray-400 hidden sm:block">
                    Showing {{ filteredPolls.length }} of {{ metrics.polls.length }} polls
                </div>
            </div>
        </header>

        <div v-if="loading" class="flex justify-center py-12">
            <loading-graphic />
        </div>

        <div v-else>

            <!-- Trend Chart (Moved Above Rollup) -->
            <div class="card p-6 mb-8 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                <h2 class="text-lg font-bold mb-4">Vote Trends</h2>
                <div class="h-64">
                    <poll-trend-chart 
                        :labels="metrics.chart.labels" 
                        :datasets="metrics.chart.datasets" 
                    />
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="card p-4 flex items-center gap-4 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                    <div class="p-3 bg-blue-50 dark:bg-blue-900/20 rounded-full">
                        <statamic-icon name="charts" class="w-6 h-6 text-blue-600" />
                    </div>
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wider text-gray-500">Total Polls</div>
                        <div class="text-2xl font-bold" v-text="filteredRollup.total_polls" />
                    </div>
                </div>
                <div class="card p-4 flex items-center gap-4 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                    <div class="p-3 bg-green-50 dark:bg-green-900/20 rounded-full">
                        <statamic-icon name="check" class="w-6 h-6 text-green-600" />
                    </div>
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wider text-gray-500">Active Polls</div>
                        <div class="text-2xl font-bold" v-text="filteredRollup.active_polls" />
                    </div>
                </div>
                <div class="card p-4 flex items-center gap-4 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
                    <div class="p-3 bg-purple-50 dark:bg-purple-900/20 rounded-full">
                        <statamic-icon name="users" class="w-6 h-6 text-purple-600" />
                    </div>
                    <div>
                        <div class="text-xs font-semibold uppercase tracking-wider text-gray-500">Total Votes</div>
                        <div class="text-2xl font-bold" v-text="filteredRollup.total_votes" />
                    </div>
                </div>
            </div>

            <!-- Poll Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <poll-card 
                    v-for="poll in filteredPolls" 
                    :key="poll.id" 
                    :poll="poll" 
                    @select="openPoll(poll)"
                />
            </div>
            <div v-if="filteredPolls.length === 0" class="text-center py-12 border border-dashed border-gray-200 dark:border-gray-800 rounded-xl text-gray-500">
                No {{ filter }} polls found.
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
