// js/modules/state.js

let globalState = {
    userData: null,
    isFetching: false,
    pollingInterval: null
};

export function getUserData() {
    return globalState.userData;
}

export function setUserData(data) {
    globalState.userData = data;
}

export function getIsFetching() {
    return globalState.isFetching;
}

export function setIsFetching(value) {
    globalState.isFetching = value;
}

export function setPollingInterval(intervalId) {
    globalState.pollingInterval = intervalId;
}

export function getPollingInterval() {
    return globalState.pollingInterval;
}

export function clearPollingInterval() {
    if (globalState.pollingInterval) {
        clearInterval(globalState.pollingInterval);
        globalState.pollingInterval = null;
    }
}