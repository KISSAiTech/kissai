function ensureStyleSheet() {
    if (document.styleSheets.length === 0 || !document.styleSheets[0].cssRules) {
        const style = document.createElement('style');
        document.head.appendChild(style);
    }
    return document.styleSheets[0];
}

// Then use this in your updateCssProperty function:
const firstStyleSheet = ensureStyleSheet();

// Function to replace ** with <b> and </b> tags
function formatBoldText(text) {
    let inBold = false;
    return text.replace(/\*\*/g, () => {
        inBold = !inBold;
        return inBold ? '<b>' : '</b>';
    });
}

function process_script_from_response(responseData) {
    if (responseData.hasOwnProperty('script') && responseData.script.trim() !== '') {
        const fn = new Function(responseData.script);
        if (responseData.hasOwnProperty('delay')) {
            setTimeout(fn, responseData.delay);
        } else {
            fn();
        }
    }
}

function updateCssProperty(selector, pseudoElement, property, value, priority = '') {
    const fullSelector = `${selector}::${pseudoElement}`;
    let found = false;
    
    // Search through stylesheets for the rule
    for (const sheet of document.styleSheets) {
        if (sheet.cssRules) {  // Ensure the sheet has rules and is accessible
            for (const rule of sheet.cssRules) {
                if (rule.selectorText === fullSelector) {
                    rule.style.setProperty(property, value, priority);
                    found = true;
                    break;
                }
            }
        }
        if (found) break;
    }
    
    // If not found, add the new rule to the first stylesheet
    if (!found) {
        try {
            const firstStyleSheet = document.styleSheets[0];
            const ruleText = `${fullSelector} { ${property}: ${value} ${priority}; }`;
            firstStyleSheet.insertRule(ruleText, firstStyleSheet.cssRules.length);
        } catch (error) {
            console.error("Failed to insert CSS rule:", error);
        }
    }
}

function updateCssProperties(jsonString) {
    const cssUpdates = JSON.parse(jsonString);
    
    for (const update of cssUpdates) {
        const { selector, pseudoElement, property, value, priority = '' } = update;
        const fullSelector = `${selector}::${pseudoElement}`;
        let found = false;
        
        // Search through stylesheets for the rule
        for (const sheet of document.styleSheets) {
            if (sheet.cssRules) {  // Ensure the sheet has rules and is accessible
                for (const rule of sheet.cssRules) {
                    if (rule.selectorText === fullSelector) {
                        rule.style.setProperty(property, value, priority);
                        found = true;
                        break;
                    }
                }
            }
            if (found) break;
        }
        
        // If not found, add the new rule to the first stylesheet
        if (!found) {
            try {
                const firstStyleSheet = document.styleSheets[0];
                const ruleText = `${fullSelector} { ${property}: ${value} ${priority}; }`;
                firstStyleSheet.insertRule(ruleText, firstStyleSheet.cssRules.length);
            } catch (error) {
                console.error("Failed to insert CSS rule:", error);
            }
        }
    }
}

function get_kissai_widget_atts_selector(container_id) {
    return '#' + container_id + ' input[name="kissai_widget_atts"]';
}

function get_kissai_widget_atts(container_id) {
    $ = jQuery;
    var kissai_widget_atts = $(get_kissai_widget_atts_selector(container_id)).val();
    return kissai_widget_atts;
}

function update_kissai_widget_atts(container_id, encoded_atts) {
    $ = jQuery;
    $(get_kissai_widget_atts_selector(container_id)).val(encoded_atts);
}

function getWidgetAtts(container_id) {
    var encodedAtts = get_kissai_widget_atts(container_id);
    if (encodedAtts) {
        try {
            const jsonAtts = atob(encodedAtts); // Decode from Base64
            const atts = JSON.parse(jsonAtts); // Parse the JSON string into an object
            return atts;
        } catch (error) {
            console.error("Failed to decode and parse widget attributes:", error);
        }
    } else {
        console.warn("No input element found for the provided selector:", selector);
    }
    return null; // or return an empty object {} as needed
}

function getCurrentUnixTimestamp() {
    return Math.floor(Date.now() / 1000);
}

function convertUnixToUTC(unixTimestamp) {
    // Create a Date object from the Unix timestamp (multiply by 1000 to convert seconds to milliseconds)
    const date = new Date(unixTimestamp * 1000);

    // Format the date components
    const year = date.getUTCFullYear();
    const month = String(date.getUTCMonth() + 1).padStart(2, '0'); // Months are zero-based, so we add 1
    const day = String(date.getUTCDate()).padStart(2, '0');
    const hours = String(date.getUTCHours()).padStart(2, '0');
    const minutes = String(date.getUTCMinutes()).padStart(2, '0');
    const seconds = String(date.getUTCSeconds()).padStart(2, '0');

    // Combine into the desired format: yyyy-mm-dd hh:mm:ss
    return `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
}


function convertToLocaleDateTime(serverDateTime) {
    // Create a Date object using the datetime string from the server
    if (serverDateTime.charAt(serverDateTime.length - 1) !== 'Z') {
        var date = new Date(serverDateTime + 'Z');  // Appending 'Z' assumes UTC time
    }
    else {
        var date = new Date(serverDateTime);
    }

    // Format the date and time in a user-friendly way
    var options = {
        year: 'numeric', month: 'long', day: 'numeric',
        hour: '2-digit', minute: '2-digit', second: '2-digit',
        hour12: false
    };

    // Use toLocaleString with options for formatting
    var converted = date.toLocaleString(undefined, options);
    if (converted.includes('Invalid Date')) {
        return serverDateTime;
    }
    return converted;
}

function updateElementTimestamp(element) {
    var $ = jQuery;
    var serverDateTime = $(element).text(); // Get the current content, which is the datetime string
    var localDateTime = convertToLocaleDateTime(serverDateTime); // Convert it to local datetime

    // Check if the converted datetime is valid
    if (isNaN(new Date(serverDateTime).getTime())) {
        // If the date is invalid, keep the original text
        return;
    }

    $(element).text(localDateTime); // Replace the content with the local datetime
}

function updateTimestamps() {
    var $ = jQuery;
    $('.kissai-timestamp').each(function() {
        updateElementTimestamp(this);
    });
}

jQuery(document).ready(updateTimestamps); // Run when the document is fully loaded

function highlightSearchText(selector, searchPhrase) {
    var $ = jQuery;

    // Split the search phrase into individual terms (words or phrases)
    const searchTerms = [];
    const regex = /"([^"]+)"|(\S+)/g;
    let match;

    // Use the jQuery selector to target the elements to highlight
    const elementsToHighlight = $(selector);

    if (searchPhrase && searchPhrase.trim() !== '') {
        while ((match = regex.exec(searchPhrase)) !== null) {
            if (match[1]) {
                // Exact phrase search (matched in quotes)
                searchTerms.push(match[1]);
            } else {
                // Single word search
                searchTerms.push(match[0]);
            }
        }

        elementsToHighlight.each(function() {
            const element = $(this)[0]; // Get the DOM element from jQuery
            removeHighlight(element); // First, remove any existing highlights

            searchTerms.forEach(term => {
                const escapedTerm = term.replace(/[-\/\\^$*+?.()|[\]{}]/g, '\\$&');
                const regex = new RegExp(`(${escapedTerm})`, 'gi'); // Case-insensitive match
                highlightTextNodes(element, regex);
            });
        });
    }
    else {
        elementsToHighlight.each(function() {
            const element = $(this)[0]; // Get the DOM element from jQuery
            removeHighlight(element); // First, remove any existing highlights
        });
    }
}

// Helper function to highlight text nodes only
function highlightTextNodes(element, regex) {
    if (element.nodeType === Node.TEXT_NODE) {
        const text = element.nodeValue;
        const highlightedText = text.replace(regex, '<span class="highlight">$1</span>');
        if (highlightedText !== text) {
            const wrapper = document.createElement('span');
            wrapper.innerHTML = highlightedText;
            element.parentNode.replaceChild(wrapper, element);
        }
    } else if (element.nodeType === Node.ELEMENT_NODE && element.childNodes) {
        element.childNodes.forEach(child => highlightTextNodes(child, regex));
    }
}

// Helper function to remove existing highlights
function removeHighlight(element) {
    var $ = jQuery;
    $(element).find('span.highlight').each(function() {
        const parent = this.parentNode;
        parent.replaceChild(document.createTextNode(this.textContent), this);
        parent.normalize(); // Merges adjacent text nodes
    });
}


// Debounce function to limit how often highlightSearchText is called
function debounce(func, delay) {
    let debounceTimer;
    return function() {
        const context = this;
        const args = arguments;
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => func.apply(context, args), delay);
    };
}

function init_search_highlight(selector, targetElement) {
    var $ = jQuery;
    $(selector).on('input', debounce(function() {
        // Get the current value of the input field
        let searchPhrase = $(this).val();

        // Call the highlightSearchText function and pass the searchPhrase and targetElement
        highlightSearchText(targetElement, searchPhrase);
    }, 300)); // 300ms debounce delay
}

// Fetches the logo from the server and updates it in the DOM
function getKissAiLogo(email, type) {
    // Define the API endpoint URL
    const url = `${kissai_vars.api_base}/logo?email=${encodeURIComponent(email)}&type=${encodeURIComponent(type)}`;

    // Fetch the logo data from the server
    fetch(url, {
        method: 'GET',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${kissai_vars.api_key}`, // Replace with your actual API key
        }
    })
        .then(response => {
            if (!response.ok) {
                throw new Error(`Error: ${response.status} - ${response.statusText}`);
            }
            return response.json();
        })
        .then(data => {
            const logoElement = document.getElementById('kissai-logo');

            if (logoElement) {
                // Clear existing content
                logoElement.innerHTML = '';

                if (!data.editable) {
                    // Create an anchor element
                    const linkElement = document.createElement('a');
                    linkElement.href = data.url; // Set the link URL
                    linkElement.target = '_blank'; // Open in a new tab
                    linkElement.rel = 'noopener noreferrer';

                    // Insert the logo inside the link
                    linkElement.innerHTML = data.logo;

                    // Append the link to the logo container
                    logoElement.appendChild(linkElement);

                    // Apply styles if provided
                    if (data.style) {
                        for (const [property, value] of Object.entries(data.style)) {
                            logoElement.style[property] = value; // Dynamically apply each style property
                        }
                    }
                }

                // Check if the logo is editable or not
                console.log(`The logo is ${data.editable ? 'editable' : 'not editable'}.`);
            }
        })
        .catch(error => {
            console.error('Error fetching the logo:', error);
        });
}

// Inserts the logo into the container and calls the API to fetch the logo
function insertKissAiLogo() {
    // Find the element with the ID #kissai-widget-container
    const widgetContainer = document.getElementById('kissai-widget-container');

    // If the container exists, proceed with fetching the logo
    if (widgetContainer) {
        // Check if the logo already exists
        let logoDiv = document.getElementById('kissai-logo');

        // If it doesn't exist, create a new div element
        if (!logoDiv) {
            logoDiv = document.createElement('div');
            logoDiv.id = 'kissai-logo'; // Set the ID for the div element
        }

        // Append the logoDiv to the container (replace if necessary)
        if (!widgetContainer.contains(logoDiv)) {
            widgetContainer.appendChild(logoDiv); // Append if not already present
        }

        // Call the function to get the logo from the server
        getKissAiLogo(kissai_vars.user_email, 'wide');
    }
}


// Call the function to insert the logo
jQuery(document).ready(function($) {
    insertKissAiLogo();
});

function requestUserToken(email, callback) {
    jQuery.ajax({
        url: kissai_vars.ajax_url,
        type: 'POST',
        dataType: 'json',
        data: {
            action: 'request_user_token',
            nonce: kissai_vars.nonce, // assuming nonce is added to kissai_vars
            email: email
        },
        success: function(response) {
            if (response.success && response.data.token) {
                const token = response.data.token;
                const endpoint = response.data.endpoint;
                // Call the callback with token and endpoint
                if (callback) callback(token, endpoint);
            } else {
                console.error('Failed to retrieve token:', response.data.message);
                callback(null, null);
            }
        },
        error: function(error) {
            console.error('AJAX error:', error);
            callback(null, null);
        }
    });
}

function init_open_page_button(className, page) {
    jQuery(className).on('click', function(e) {
        e.preventDefault();

        // Retrieve email from data attribute
        const email = jQuery(this).data('user-email');
        if (!email) {
            console.error('No email provided');
            return;
        }

        // Open a new blank window immediately
        const newWindow = window.open('', '_blank');

        // URL encode the redirect parameter
        const encodedPage = encodeURIComponent(page);

        // Fetch token via AJAX
        requestUserToken(email, function(token, endpoint) {
            if (token && endpoint) {
                // Once token is received, update the new window's location
                newWindow.location.href = `${endpoint}?token=${token}&redirect=${encodedPage}`;
            } else {
                console.error('Failed to retrieve token or endpoint');
                newWindow.alert('We could not retrieve the necessary information from the server to proceed. Please try again or contact support.');
                newWindow.close(); // Close the window if token retrieval failed
            }
        });
    });
}