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
import InboxPollForm from './InboxPollForm.vue';

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
                options: ['', ''],
                tags: []
            }
        }
    },
    mounted() {
        if (typeof Statamic !== 'undefined' && Statamic.$activitypub) {
            Statamic.$activitypub.bus.$on('activitypub:inbox:create-poll', this.openPollModal);
        }
    },
    beforeDestroy() {
        if (typeof Statamic !== 'undefined' && Statamic.$activitypub) {
            Statamic.$activitypub.bus.$off('activitypub:inbox:create-poll', this.openPollModal);
        }
    },
    methods: {
        openPollModal() {
            this.isCreatingPoll = true;
            this.newPoll = {
                actor: this.actors[0]?.id || null,
                content: '',
                multiple_choice: false,
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

            console.log('QuestionModals: submitPoll payload:', JSON.stringify({
                actor: this.newPoll.actor,
                content: this.newPoll.content,
                options: opts,
                multiple_choice: this.newPoll.multiple_choice,
                tags: this.newPoll.tags
            }));

            this.creating = true;
            this.$axios.post(this.storePollUrl, {
                actor: this.newPoll.actor,
                content: this.newPoll.content,
                options: opts,
                multiple_choice: this.newPoll.multiple_choice,
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
