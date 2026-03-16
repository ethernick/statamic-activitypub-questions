<template>
    <div v-if="note.type === 'question'" class="mt-3 p-3 bg-gray-50 dark:bg-gray-800 rounded-lg border border-gray-100 dark:border-gray-700">
        <div class="flex justify-between items-center mb-2">
            <span class="text-xs font-bold uppercase text-gray-500 flex items-center gap-1">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
                Poll
            </span>
            <span v-if="note.closed" class="text-xs font-bold text-red-500">Closed</span>
            <span v-else-if="note.end_time" class="text-xs text-gray-400">Ends {{ formatTime(note.end_time) }}</span>
        </div>

        <!-- Open Ended Question -->
        <div v-if="!note.options || note.options.length === 0" class="flex flex-col gap-2">
            <template v-if="hasVoted(note) || note.closed">
                <div class="p-3 bg-gray-100 dark:bg-gray-700 rounded text-sm text-gray-500 italic">
                    Open-ended responses are hidden.
                </div>
            </template>
            <template v-else>
                <textarea 
                    v-model="openEndedResponse" 
                    class="input-text w-full text-sm" 
                    rows="3" 
                    placeholder="Type your answer..."
                    :disabled="isVoting"
                ></textarea>
                <div class="flex justify-end mt-2">
                    <button @click="submitVote(note)" :disabled="isVoting || !openEndedResponse.trim()" class="btn-primary text-xs px-3 py-1.5">
                        {{ isVoting ? 'Submitting...' : 'Submit Answer' }}
                    </button>
                </div>
            </template>
        </div>

        <!-- Standard Poll (Options) -->
        <div v-else class="flex flex-col gap-2">
                <div v-for="(opt, idx) in note.options" :key="idx" class="relative">
                <!-- Show Results -->
                <template v-if="hasVoted(note) || note.closed">
                    <div class="absolute inset-y-0 left-0 bg-blue-100 dark:bg-blue-900/30 rounded"
                            :style="{ width: getPercentage(opt, note) + '%' }"></div>
                    <div class="relative flex items-center justify-between p-2 border border-blue-200 dark:border-blue-800 rounded z-10 transition-all hover:bg-blue-50/50 dark:hover:bg-blue-900/20">
                        <span class="text-sm font-medium">{{ opt.name }}</span>
                        <span class="text-xs text-gray-500">
                            {{ getPercentage(opt, note) }}% ({{ opt.count }})
                        </span>
                    </div>
                </template>

                <!-- Vote Form -->
                <template v-else>
                    <label class="relative flex items-center p-2 border border-gray-200 dark:border-gray-700 rounded hover:bg-white dark:hover:bg-gray-700 cursor-pointer transition-colors shadow-sm hover:shadow">
                        <input 
                            v-if="note.multiple_choice" 
                            type="checkbox" 
                            :value="opt" 
                            v-model="selectedOptions" 
                            class="mr-2 text-blue-600 focus:ring-blue-500 rounded"
                        >
                        <input 
                            v-else 
                            type="radio" 
                            :value="opt" 
                            v-model="selectedOptions" 
                            class="mr-2 text-blue-600 focus:ring-blue-500"
                        >
                        <span class="text-sm font-medium">{{ opt.name }}</span>
                    </label>
                </template>
                </div>

                <!-- Submit Button -->
                <div v-if="!hasVoted(note) && !note.closed" class="flex justify-end mt-2">
                <button @click="submitVote(note)" :disabled="isVoting || (!openEndedResponse && selectedOptions.length === 0 && !selectedOptions.name)" class="btn-primary text-xs px-3 py-1.5 shadow-sm">
                    {{ isVoting ? 'Voting...' : 'Vote' }}
                </button>
                </div>
        </div>
        
        <div class="mt-2 text-xs text-gray-400 text-right">
            {{ note.voters_count }} votes
        </div>
    </div>
</template>

<script>
export default {
    name: 'PollBox',
    props: {
        note: {
            type: Object,
            required: true
        },
        permissions: {
            type: Object,
            default: () => ({})
        },
        actors: {
            type: Array,
            default: () => []
        },
        storeNoteUrl: {
            type: String,
            default: null
        }
    },
    data() {
        return {
            isVoting: false,
            selectedOptions: [],
            openEndedResponse: ''
        }
    },
    methods: {
        hasVoted(note) {
             return note.has_voted || note.closed;
        },
        getPercentage(opt, note) {
            if (!note.voters_count || note.voters_count === 0) return 0;
            return Math.round((opt.count / note.voters_count) * 100);
        },
        formatTime(dateStr) {
            return new Date(dateStr).toLocaleString();
        },
        submitVote(note) {
            if (this.isVoting) return;
            
            let votes = [];
            
            if (!note.options || note.options.length === 0) {
                if (!this.openEndedResponse.trim()) return;
                 votes.push(this.openEndedResponse);
            } else {
                if (Array.isArray(this.selectedOptions)) {
                     votes = this.selectedOptions.map(o => o.name);
                } else if (this.selectedOptions) {
                    votes = [this.selectedOptions.name];
                }
                if (votes.length === 0) return;
            }

            this.isVoting = true;

            // Direct axios call if URL is available
            if (this.storeNoteUrl) {
                const actorId = this.actors && this.actors[0] ? this.actors[0].id : null;
                
                this.$axios.post(this.storeNoteUrl + '/vote', {
                    poll: note.id,
                    choices: votes,
                    actor: actorId
                })
                .then(() => {
                    this.isVoting = false;
                    note.has_voted = true;
                    // If note.voters_count is not reactive, we might need to force update or use Vue.set
                    // But usually props in such setups are reactive objects.
                    note.voters_count = (note.voters_count || 0) + 1;
                    if (note.options) {
                        votes.forEach(vName => {
                            const opt = note.options.find(o => o.name === vName);
                            if (opt) opt.count = (opt.count || 0) + 1;
                        });
                    }
                })
                .catch(e => {
                    this.isVoting = false;
                    const message = e.response && e.response.data.message ? e.response.data.message : e.message;
                    alert(message);
                });
                return;
            }

            // Fallback for older core versions if needed (emit)
            this.$emit('vote', {
                note: note,
                option: { name: votes[0] }, // Simplified fallback
                callback: (success) => {
                     this.isVoting = false;
                     if (success) {
                        note.has_voted = true;
                        note.voters_count = (note.voters_count || 0) + 1;
                     }
                }
            });
        }
    }
}
</script>
