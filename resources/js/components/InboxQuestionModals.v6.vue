<template>
    <div>
        <inbox-poll-form
            :open="isCreatingPoll"
            :form="newPoll"
            :actors="actors"
            :is-editing="!!editingPollId"
            :loading="creating"
            :hashtag-enabled="hashtagEnabled"
            :hashtag-taxonomy="hashtagTaxonomy"
            :search-terms-url="searchTermsUrl"
            @close="closePollModal"
            @submit="submitPoll"
        />

        <poll-drawer 
            ref="drawer" 
            :poll="viewingPoll" 
            :metrics-url="pollMetricsUrl"
            :voters-url="pollVotersUrl"
            :close-url="pollCloseUrl"
            @closed="viewingPoll = null"
        />
    </div>
</template>

<script>
import InboxPollForm from './InboxPollForm.v6.vue';
import PollDrawer from './PollDrawer.vue';

export default {
    name: 'InboxQuestionModals',
    components: {
        InboxPollForm,
        PollDrawer
    },
    props: {
        actors: Array,
        storePollUrl: String,
        hashtagEnabled: Boolean,
        hashtagTaxonomy: String,
        searchTermsUrl: String
    },
    data() {
        return {
            isCreatingPoll: false,
            creating: false,
            newPoll: {
                actor: null,
                content: '',
                multiple_choice: false,
                date: this.getInitialDate(),
                options: ['', ''],
                tags: []
            },
            editingPollId: null,
            viewingPoll: null,
        }
    },
    computed: {
        pollMetricsUrl() {
            return (Statamic.cpRoot || '/cp') + '/activitypub/polls/metrics';
        },
        pollVotersUrl() {
            return (Statamic.cpRoot || '/cp') + '/activitypub/polls/ID_PLACEHOLDER/voters';
        },
        pollCloseUrl() {
            return (Statamic.cpRoot || '/cp') + '/activitypub/polls/ID_PLACEHOLDER/close';
        }
    },
    mounted() {
        if (typeof Statamic !== 'undefined' && Statamic.$activitypub) {
            // Vue 3 compatible event listening if using a mitt-like bus
            if (typeof Statamic.$activitypub.bus.on === 'function') {
                Statamic.$activitypub.bus.on('activitypub:inbox:create-poll', this.openPollModal);
                Statamic.$activitypub.bus.on('activitypub:inbox:edit-poll', this.openEditPollModal);
                Statamic.$activitypub.bus.on('activitypub-open-poll-drawer', this.openViewPollDrawer);
            } else if (typeof Statamic.$activitypub.bus.$on === 'function') {
                Statamic.$activitypub.bus.$on('activitypub:inbox:create-poll', this.openPollModal);
                Statamic.$activitypub.bus.$on('activitypub:inbox:edit-poll', this.openEditPollModal);
                Statamic.$activitypub.bus.$on('activitypub-open-poll-drawer', this.openViewPollDrawer);
            }
        }
    },
    beforeUnmount() {
        if (typeof Statamic !== 'undefined' && Statamic.$activitypub) {
             if (typeof Statamic.$activitypub.bus.off === 'function') {
                Statamic.$activitypub.bus.off('activitypub:inbox:create-poll', this.openPollModal);
                Statamic.$activitypub.bus.off('activitypub:inbox:edit-poll', this.openEditPollModal);
                Statamic.$activitypub.bus.off('activitypub-open-poll-drawer', this.openViewPollDrawer);
            } else if (typeof Statamic.$activitypub.bus.$off === 'function') {
                Statamic.$activitypub.bus.$off('activitypub:inbox:create-poll', this.openPollModal);
                Statamic.$activitypub.bus.$off('activitypub:inbox:edit-poll', this.openEditPollModal);
                Statamic.$activitypub.bus.$off('activitypub-open-poll-drawer', this.openViewPollDrawer);
            }
        }
    },
    methods: {
        openViewPollDrawer(note) {
            this.viewingPoll = note;
            this.$nextTick(() => {
                if (this.$refs.drawer && typeof this.$refs.drawer.open === 'function') {
                    this.$refs.drawer.open();
                } else if (this.$refs.drawer) {
                    this.$refs.drawer.isOpen = true;
                }
            });
        },
        getInitialDate() {
            const now = new Date();
            // Default to 7 days from now
            now.setDate(now.getDate() + 7);
            now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
            return now.toISOString().slice(0, 16);
        },
        openPollModal() {
            this.isCreatingPoll = true;
            this.editingPollId = null;
            this.newPoll = {
                actor: this.actors[0]?.id || null,
                content: '',
                multiple_choice: false,
                duration: 10080,
                date: this.getInitialDate(),
                options: ['', ''],
                tags: []
            };
        },
        openEditPollModal(poll) {
            this.isCreatingPoll = true;
            this.editingPollId = poll.id;
            
            // Extract options and info from the poll safely
            let json = {};
            if (poll.activitypub_json) {
                json = typeof poll.activitypub_json === 'string' 
                    ? JSON.parse(poll.activitypub_json || '{}') 
                    : (poll.activitypub_json || {});
            }
            
            const oneOf = json.oneOf || json.anyOf || [];

            this.newPoll = {
                actor: poll.actor.id,
                content: poll.content_raw || poll.content || json.content || '',
                multiple_choice: !!json.anyOf,
                duration: 10080, // We can calculate this from endTime if needed
                date: poll.end_time || this.getInitialDate(),
                options: oneOf.map(o => (o.name || o)), // Handling if string options
                tags: poll.tags || []
            };
        },
        closePollModal() {
            this.isCreatingPoll = false;
            this.editingPollId = null;
        },
        submitPoll() {
            if (this.creating) return;
            if (!this.newPoll.content.trim()) return;
            
            const opts = this.newPoll.options.filter(o => o.trim());
            if (opts.length < 2 && opts.length > 0) {
                 alert('A poll needs at least 2 options, or none for open-ended.');
                 return;
            }

            // Calculate duration in minutes from End Date
            const now = new Date();
            const endDate = new Date(this.newPoll.date);
            let duration = Math.round((endDate - now) / 60000);
            
            if (duration < 1) {
                alert('End date must be in the future.');
                return;
            }

            this.creating = true;

            const method = this.editingPollId ? 'put' : 'post';
            const url = this.editingPollId 
                ? cp_url(`activitypub/polls/${this.editingPollId}`) 
                : this.storePollUrl;

            this.$axios[method](url, {
                actor: this.newPoll.actor,
                content: this.newPoll.content,
                options: opts,
                multiple_choice: this.newPoll.multiple_choice,
                duration: duration,
                date: now.toISOString(), // Publish now
                tags: this.newPoll.tags
            })
            .then(() => {
                this.closePollModal();
                this.$emit('submit-success');
            })
            .catch(e => {
                const message = e.response && e.response.data.message ? e.response.data.message : e.message;
                alert(message);
            })
            .finally(() => this.creating = false);
        }
    }
}
</script>
