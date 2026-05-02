import PollDashboard from './components/PollDashboard.vue';
import PollStack from './components/PollStack.vue';
import PollInboxAction from './components/PollInboxAction.vue';
import PollBox from './components/PollBox.vue';
import InboxNewPoll from './components/InboxNewPoll.vue';
import PollInboxModals from './components/PollInboxModals.vue';

const boot = () => {
    if (typeof Statamic !== 'undefined' && Statamic.$activitypub) {
        
        Statamic.booting(() => {
            Statamic.$components.register('poll-dashboard', PollDashboard);
            Statamic.$components.register('poll-stack', PollStack);
        });

        Statamic.$activitypub.hooks.register('inbox-activity-Question', {
            component: PollBox,
            priority: 10
        });

        Statamic.$activitypub.hooks.register('inbox-new-dropdown', {
            component: InboxNewPoll,
            priority: 10
        });

        Statamic.$activitypub.hooks.register('inbox-modals', {
            component: PollInboxModals,
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
