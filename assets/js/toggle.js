
const chatbotToggler = document.getElementById("chatbot-toggler");
const chatbotFrame = document.getElementById("chatbotFrame");


/**
 * Creates a persistent store for a given key with an initial value.
 *
 * This function attempts to retrieve existing data from sessionStorage.
 * If no data exists, it uses the provided initial value. It returns an
 * object with methods to get, set, and check existence of the value.
 *
 * @param {string} key - The key to store the value under in sessionStorage.
 * @param {*} initialValue - The initial value to use if none is found.
 * @returns {object} An object with get, set, and exists functions.
 */
function persistentStore(key, initialValue) {
  
	// Attempt to read existing data from sessionStorage
	let stored = sessionStorage.getItem(key);
	let value = stored ? JSON.parse(stored) : initialValue;

	// Updates the value and saves it to sessionStorage.
	function set(newValue) {
   	value = newValue;
   	sessionStorage.setItem(key, JSON.stringify(newValue));
	}

   // Retrieves the current value.
	function get() {
   	return value;
	}

	// Checks whether the given key exists in sessionStorage.
	function exists() {
   	return sessionStorage.getItem(key) !== null;
	}

  return { get, set, exists };
}

const isOpenWidget = persistentStore('zoltiq-chatbot-isOpenWidget', false);

// If the widget open state exists in sessionStorage, use it to open/close the widget accordingly.
if (isOpenWidget.exists()) {
	let state = isOpenWidget.get();
	openWidget(state);
} 

// If the chatbotFrame is currently visible, add the "show-chatbot" class to the toggler.       
if(chatbotFrame.style.display === "block") {
	chatbotToggler.classList.add("show-chatbot");
} else {
	chatbotFrame.style.display = "none";
}

// Add a click event listener to the chatbot toggler.        
chatbotToggler.addEventListener("click", () => {
	let state = false;
  
	if(chatbotFrame.style.display === "none") {
   	state = true ;
   	// jeśli inline script w iframe dołączył window.__initChatbotDraggable
   	if (chatbotFrame && chatbotFrame.contentWindow && typeof chatbotFrame.contentWindow.__initChatbotDraggable === 'function') {
   	   chatbotFrame.contentWindow.__initChatbotDraggable();
   	}
	} 
	openWidget(state)
	isOpenWidget.set(state);
});

// Listen for messages from the chatbot iframe to close the widget. 
window.addEventListener("message", (e) => {
  	if (e.data === "close_chatbot") {
		openWidget(false)
		isOpenWidget.set(false);
	}
});

/**
 * Opens or closes the chatbot widget based on the provided state.
 *
 * If state is true, the widget is opened; otherwise, it is closed.
 *
 * @param {boolean} state - True to open the widget, false to close it.
 */
function openWidget(state) {
	if (state) {
		chatbotToggler.classList.add("show-chatbot");
		chatbotFrame.style.display = "block";
	} else {
		chatbotToggler.classList.remove("show-chatbot");
		chatbotFrame.style.display = "none";
	}
} 