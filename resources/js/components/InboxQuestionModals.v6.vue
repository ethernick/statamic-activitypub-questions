<template>
    <div>
        <inbox-poll-form
            :open="isCreatingPoll"
            :form="newPoll"
            :actors="actors"
            :loading="creating"
            :hashtag-enabled="hashtagEnabled"
            :hashtag-taxonomy="hashtagTaxonomy"
            :search-terms-url="searchTermsUrl"
            @close="closePollModal"
            @submit="submitPoll"
        />
    </div>
</template>

<script>
import InboxPollForm from './InboxPollForm.v6.vue';

export default {
    name: 'InboxQuestionModals',
    components: {
        InboxPollForm
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
                duration: 10080,
                date: this.getInitialDate(),
                options: ['', ''],
                tags: []
            }
        }
    },
    mounted() {
        if (typeof Statamic !== 'undefined' && Statamic.$activitypub) {
            // Vue 3 compatible event listening if using a mitt-like bus
            if (typeof Statamic.$activitypub.bus.on === 'function') {
                Statamic.$activitypub.bus.on('activitypub:inbox:create-poll', this.openPollModal);
            } else if (typeof Statamic.$activitypub.bus.$on === 'function') {
                Statamic.$activitypub.bus.$on('activitypub:inbox:create-poll', this.openPollModal);
            }
        }
    },
    beforeUnmount() {
        if (typeof Statamic !== 'undefined' && Statamic.$activitypub) {
             if (typeof Statamic.$activitypub.bus.off === 'function') {
                Statamic.$activitypub.bus.off('activitypub:inbox:create-poll', this.openPollModal);
            } else if (typeof Statamic.$activitypub.bus.$off === 'function') {
                Statamic.$activitypub.bus.$off('activitypub:inbox:create-poll', this.openPollModal);
            }
        }
    },
    methods: {
        getInitialDate() {
            const now = new Date();
            // Default to 7 days from now
            now.setDate(now.getDate() + 7);
            now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
            return now.toISOString().slice(0, 16);
        },
        openPollModal() {
            this.isCreatingPoll = true;
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
        closePollModal() {
            this.isCreatingPoll = false;
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
            this.$axios.post(this.storePollUrl, {
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
