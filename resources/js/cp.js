import PollBox from './components/PollBox.vue';
import InboxNewPoll from './components/InboxNewPoll.vue';

const boot = () => {
    if (typeof Statamic !== 'undefined' && Statamic.$activitypub) {
        Statamic.$activitypub.hooks.register('inbox-note-content', {
            component: PollBox,
            priority: 10
        });

        Statamic.$activitypub.hooks.register('inbox-new-dropdown', {
            component: InboxNewPoll,
            priority: 10
        });

        Statamic.$activitypub.hooks.register('inbox-modals', {
            component: InboxQuestionModals,
            priority: 10
        });
    }
};

if (typeof Statamic !== 'undefined') {
    boot();
}
