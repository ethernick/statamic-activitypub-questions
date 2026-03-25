<template>
    <inbox-stack :open="isOpen" title="Poll Details" @closed="close">
        
        <template v-if="activePoll">
            <!-- Header (Content & Actions) -->
            <div class="mb-6 flex items-start justify-between">
                <div>
                    <div class="font-medium text-base prose dark:prose-invert prose-sm" v-html="activePoll.content || activePoll.title" />
                    <div class="text-xs text-gray-500 uppercase tracking-widest mt-4">
                        <span v-if="activePoll.closed" class="text-red-500 font-bold">Closed</span>
                        <span v-else class="text-green-600 font-bold">Active</span>
                        &bull; {{ activePoll.voters_count }} votes
                    </div>
                </div>
                <div class="flex items-center gap-2 shrink-0 ml-4">
                    <button 
                        v-if="canManage && !activePoll.closed"
                        class="btn btn-danger" 
                        @click="closePoll"
                        :disabled="isClosing"
                    >
                        {{ isClosing ? 'Closing...' : 'Close Poll' }}
                    </button>
                    <span v-else-if="activePoll.closed" class="px-3 py-1 bg-gray-100 dark:bg-gray-900 text-gray-500 rounded text-xs font-bold uppercase tracking-widest">Closed</span>
                </div>
            </div>

            <!-- Content -->
            <div class="space-y-8">
                
                <!-- Results Section -->
                <section>
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-4">Detailed Results</h3>
                    <div class="space-y-4">
                        <div v-for="(option, idx) in activePoll.options" :key="idx" class="p-4 border border-gray-100 dark:border-gray-800 bg-gray-50/50 dark:bg-gray-800/30 rounded">
                            <div class="flex justify-between items-end mb-2">
                                <div class="font-bold text-gray-800 dark:text-gray-100" v-text="option.name" />
                                <div class="text-right">
                                    <div class="text-lg font-bold text-blue-600" v-text="calculatePercentage(option.count) + '%'" />
                                    <div class="text-xs text-gray-500" v-text="option.count + ' votes'" />
                                </div>
                            </div>
                            <div class="h-2 w-full bg-gray-200 dark:bg-gray-700 rounded-full overflow-hidden">
                                <div 
                                    class="h-full bg-blue-500" 
                                    :style="{ width: calculatePercentage(option.count) + '%' }"
                                />
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Trend Section -->
                <section>
                    <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500 mb-4">Growth Over Time</h3>
                    <div class="h-48 border border-gray-100 dark:border-gray-800 bg-gray-50/30 dark:bg-gray-800/20 p-4">
                        <poll-trend-chart 
                            v-if="trendData"
                            :labels="trendData.labels" 
                            :datasets="trendData.datasets" 
                        />
                    </div>
                </section>

                <!-- Voters Section -->
                <section>
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-gray-500">Voters</h3>
                        <span class="text-xs text-gray-400" v-text="activePoll.voters_count + ' total'" />
                    </div>
                    <div v-if="votersLoading" class="flex justify-center py-4">
                        <loading-graphic />
                    </div>
                    <div v-else-if="voters.length" class="space-y-3">
                        <div v-for="voter in voters" :key="voter.id" class="flex items-center gap-3 p-3 border border-gray-100 dark:border-gray-800 bg-white dark:bg-gray-800 rounded-lg">
                            <div class="w-8 h-8 rounded-full bg-gray-200 dark:bg-gray-700 flex items-center justify-center text-xs font-bold capitalize overflow-hidden">
                                <img v-if="voter.avatar" :src="voter.avatar" class="w-full h-full object-cover" />
                                <span v-else>{{ voter.handle?.split('@')[1]?.substring(0, 1) || voter.name?.substring(0, 1) || '?' }}</span>
                            </div>
                            <div class="text-sm truncate">
                                <div class="font-medium text-gray-900 dark:text-white" v-text="voter.name || voter.handle" />
                                <div v-if="voter.handle" class="text-xs text-gray-500" v-text="voter.handle" />
                            </div>
                        </div>
                    </div>
                    <div v-else class="text-sm text-gray-500 italic p-4 text-center border border-dashed border-gray-200 dark:border-gray-700 rounded-xl">
                        No individual voter data available for this poll.
                    </div>
                </section>
            </div>
        </template>
        
        <template #footer-end>
            <button @click="close" class="btn">Close</button>
        </template>
    </inbox-stack>
</template>

<script>
import PollTrendChart from './PollTrendChart.vue';

export default {
    props: {
        poll: Object,
        metricsUrl: String,
        votersUrl: String,
        closeUrl: String,
        actors: {
            type: Array,
            default: () => []
        },
        permissions: {
            type: Object,
            default: () => ({ manage_polls: false })
        }
    },
    components: { PollTrendChart },
    data() {
        return {
            isOpen: false,
            voters: [],
            votersLoading: false,
            activePoll: null,
            trendData: null,
            isClosing: false,
        }
    },
    computed: {
        canManage() {
            if (!this.activePoll) return false;
            if (!this.activePoll.is_internal) return false;
            if (!this.permissions.manage_polls) return false;

            // Check if poll actor matches one of our local actors
            return this.actors.some(a => 
                (a.id && a.id === this.activePoll.actor) || 
                (a.url && a.url === this.activePoll.actor)
            );
        }
    },
    watch: {
        poll: {
            handler(newPoll) {
                if (newPoll) {
                    this.formatActivePoll(newPoll);
                    this.fetchVoters();
                    this.fetchTrend();
                }
            },
            immediate: true
        }
    },
    methods: {
        fetchTrend() {
            if (!this.activePoll) return;
            
            // In a real app, we might fetch specific trend for this poll
            // For now, we'll try to get it from the parent dashboard if available
            // or we could add a dedicated endpoint. 
            // Let's assume we fetch it for simplicity.
            this.$axios.get(this.metricsUrl)
                .then(response => {
                    const allTrends = response.data.chart;
                    const myTrend = allTrends.datasets.find(ds => ds.label === this.activePoll.title);
                    if (myTrend) {
                        this.trendData = {
                            labels: allTrends.labels,
                            datasets: [myTrend]
                        };
                    }
                });
        },
        open() {
            this.isOpen = true;
            document.body.style.overflow = 'hidden';
        },
        close() {
            this.isOpen = false;
            document.body.style.overflow = '';
            this.$emit('closed');
        },
        closePoll() {
            if (!confirm('Are you sure you want to close this poll? This cannot be undone.')) return;

            this.isClosing = true;
            const url = this.closeUrl.replace('ID_PLACEHOLDER', this.activePoll.id);
            this.$axios.post(url)
                .then(response => {
                    Statamic.$toast.success('Poll closed successfully');
                    this.$emit('refresh'); // Tell parent to refresh list
                    // Update local state
                    this.activePoll.closed = true;
                })
                .catch(error => {
                    Statamic.$toast.error('Failed to close poll');
                    console.error(error);
                })
                .finally(() => {
                    this.isClosing = false;
                });
        },
        formatActivePoll(poll) {
            // Handle both internal Poll object and Inbox Note object
            if (poll.options) {
                this.activePoll = poll;
            } else if (poll.activitypub_json) {
                const json = JSON.parse(typeof poll.activitypub_json === 'string' ? poll.activitypub_json : JSON.stringify(poll.activitypub_json));
                const oneOf = json.oneOf || json.anyOf || [];
                this.activePoll = {
                    id: poll.id,
                    title: poll.title || json.name || json.summary,
                    voters_count: json.votersCount || poll.voters_count || 0,
                    actor: poll.actor || json.attributedTo || json.actor,
                    is_internal: poll.is_internal || false,
                    options: oneOf.map(opt => ({
                        name: opt.name,
                        count: opt.replies?.totalItems || 0
                    }))
                };
            }
        },
        fetchVoters() {
            if (!this.activePoll || !this.activePoll.id) return;
            
            this.votersLoading = true;
            this.voters = [];
            
            // Standard voters fetch
            const url = this.votersUrl.replace('ID_PLACEHOLDER', this.activePoll.id);
            this.$axios.get(url)
                .then(response => {
                    this.voters = response.data.voters || [];
                })
                .finally(() => {
                    this.votersLoading = false;
                });
        },
        calculatePercentage(count) {
            if (!this.activePoll || !this.activePoll.voters_count) return 0;
            return Math.round((count / this.activePoll.voters_count) * 100);
        }
    }
}
</script>
