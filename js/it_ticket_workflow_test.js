const STATUSES = [
    'Pending',
    'Received',
    'In Progress',
    'Waiting for Parts',
    'Temporary Fix',
    'Unrepairable',
    'Completed',
    'Cancelled'
];

function isValidTransition(from, to) {
    if (!STATUSES.includes(from) || !STATUSES.includes(to)) return false;
    if (from === to) return true;
    const map = {
        Pending: ['Received', 'Cancelled'],
        Received: ['In Progress', 'Waiting for Parts', 'Temporary Fix', 'Unrepairable', 'Cancelled'],
        'In Progress': ['Waiting for Parts', 'Temporary Fix', 'Unrepairable', 'Completed', 'Cancelled'],
        'Waiting for Parts': ['In Progress', 'Unrepairable', 'Completed', 'Cancelled'],
        'Temporary Fix': ['In Progress', 'Unrepairable', 'Completed', 'Cancelled'],
        Unrepairable: ['In Progress'],
        Completed: ['In Progress'],
        Cancelled: ['In Progress']
    };
    const allowed = map[from] || [];
    return allowed.includes(to);
}

function runTests() {
    const assert = (cond, msg) => {
        if (!cond) throw new Error(msg);
    };

    assert(isValidTransition('Pending', 'Received'), 'Pending -> Received should be valid');
    assert(!isValidTransition('Pending', 'In Progress'), 'Pending -> In Progress should be invalid');
    assert(isValidTransition('Pending', 'Cancelled'), 'Pending -> Cancelled should be valid');
    assert(!isValidTransition('Pending', 'Completed'), 'Pending -> Completed should be invalid');

    assert(isValidTransition('Received', 'In Progress'), 'Received -> In Progress should be valid');
    assert(isValidTransition('Received', 'Waiting for Parts'), 'Received -> Waiting for Parts should be valid');
    assert(isValidTransition('Received', 'Temporary Fix'), 'Received -> Temporary Fix should be valid');
    assert(isValidTransition('Received', 'Cancelled'), 'Received -> Cancelled should be valid');
    assert(!isValidTransition('Received', 'Pending'), 'Received -> Pending should be invalid');

    assert(isValidTransition('In Progress', 'Waiting for Parts'), 'In Progress -> Waiting for Parts should be valid');
    assert(isValidTransition('In Progress', 'Temporary Fix'), 'In Progress -> Temporary Fix should be valid');
    assert(isValidTransition('In Progress', 'Completed'), 'In Progress -> Completed should be valid');
    assert(isValidTransition('In Progress', 'Cancelled'), 'In Progress -> Cancelled should be valid');

    assert(isValidTransition('Waiting for Parts', 'In Progress'), 'Waiting for Parts -> In Progress should be valid');
    assert(isValidTransition('Waiting for Parts', 'Completed'), 'Waiting for Parts -> Completed should be valid');
    assert(isValidTransition('Waiting for Parts', 'Cancelled'), 'Waiting for Parts -> Cancelled should be valid');

    assert(isValidTransition('Temporary Fix', 'In Progress'), 'Temporary Fix -> In Progress should be valid');
    assert(isValidTransition('Temporary Fix', 'Completed'), 'Temporary Fix -> Completed should be valid');
    assert(isValidTransition('Temporary Fix', 'Cancelled'), 'Temporary Fix -> Cancelled should be valid');

    assert(isValidTransition('In Progress', 'Unrepairable'), 'In Progress -> Unrepairable should be valid');
    assert(isValidTransition('Waiting for Parts', 'Unrepairable'), 'Waiting for Parts -> Unrepairable should be valid');
    assert(isValidTransition('Temporary Fix', 'Unrepairable'), 'Temporary Fix -> Unrepairable should be valid');
    assert(isValidTransition('Unrepairable', 'In Progress'), 'Unrepairable -> In Progress (reopen) should be valid');
    assert(!isValidTransition('Unrepairable', 'Completed'), 'Unrepairable -> Completed should be invalid');

    assert(isValidTransition('Completed', 'In Progress'), 'Completed -> In Progress (reopen) should be valid');
    assert(!isValidTransition('Completed', 'Pending'), 'Completed -> Pending should be invalid');

    assert(isValidTransition('Cancelled', 'In Progress'), 'Cancelled -> In Progress (reopen) should be valid');
    assert(!isValidTransition('Cancelled', 'Pending'), 'Cancelled -> Pending should be invalid');

    STATUSES.forEach(s => {
        assert(isValidTransition(s, s), `Stay in ${s} should be valid`);
    });

    console.log('IT ticket workflow status tests passed');
}

if (typeof module !== 'undefined' && require.main === module) {
    runTests();
}
