import PollDashboard from './components/PollDashboard.vue';
import PollDrawer from './components/PollDrawer.vue';
import PollInboxAction from './components/PollInboxAction.vue';
import PollBox from './components/PollBox.vue';
import InboxNewPoll from './components/InboxNewPoll.vue';
import InboxQuestionModals from './components/InboxQuestionModals.vue';

const boot = () => {
    if (typeof Statamic !== 'undefined' && Statamic.$activitypub) {
        
        Statamic.booting(() => {
            Statamic.$components.register('poll-dashboard', PollDashboard);
            Statamic.$components.register('poll-drawer', PollDrawer);
        });

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

        // Register the chart icon hook for Inbox
        Statamic.$activitypub.hooks.register('inbox-note-actions', {
            component: PollInboxAction,
            priority: 20
        });

    } else if (typeof Statamic !== 'undefined') {
        setTimeout(boot, 10);
    }
};

boot();
