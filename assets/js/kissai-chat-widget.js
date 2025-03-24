const sseStreamStatus = {};
/*******************************************
 * 1) Shared SSE Parser: readSseStream
 *******************************************/

/**
 * Reads an SSE response from a fetch() call and invokes callbacks for each event.
 *
 * @param {Response} response The fetch() Response expected to contain SSE
 * @param {object} options    Config callbacks
 *   - onEvent(eventName, dataString): Called for every complete SSE event
 *   - onDone(): Called once the SSE stream ends
 */
async function readSseStream(response, {
    onEvent = (eventName, data) => {},
    onDone = () => {}
} = {}) {

    // If the response is not OK, read the error text
    if (!response.ok) {
        const text = await response.text();
        throw new Error(`SSE request failed: ${response.status} - ${text}`);
    }
    if (!response.body) {
        console.warn('No response body found for SSE stream');
        return;
    }

    const reader = response.body.getReader();
    const decoder = new TextDecoder('utf-8');

    let partialBuffer = '';
    let currentEventName = '';

    // Recursive function to read each chunk
    async function processChunk({ done, value }) {
        if (done) {
            // SSE stream has ended
            onDone();
            return;
        }

        const text = decoder.decode(value, { stream: true });
        const lines = text.split('\n');

        for (let line of lines) {
            const trimmed = line.trimEnd();

            if (trimmed.startsWith('event:')) {
                // If there's leftover data from the previous event, finalize it
                if (currentEventName && partialBuffer) {
                    onEvent(currentEventName, partialBuffer);
                    partialBuffer = '';
                }
                currentEventName = trimmed.slice(6).trim(); // e.g. "thread.run.requires_action"
            } else if (trimmed.startsWith('data:')) {
                partialBuffer += trimmed.slice(5).trim();
            } else if (trimmed === '') {
                // A blank line indicates the end of this SSE event
                if (currentEventName && partialBuffer) {
                    onEvent(currentEventName, partialBuffer);
                }
                // Reset for the next event
                currentEventName = '';
                partialBuffer = '';
            } else {
                // Possibly partial data
                partialBuffer += trimmed;
            }
        }

        // Keep reading
        return reader.read().then(processChunk);
    }

    // Start reading
    return reader.read().then(processChunk);
}

/*******************************************
 * 2) SSE Event Handler: handleSseEvent
 *******************************************/

/**
 * Parses and handles each SSE event (e.g., requires_action).
 * This is where you do your domain logic for each event type.
 *
 * @param {string} eventName   SSE event name (e.g. "thread.run.requires_action")
 * @param {string} dataString  The raw data from SSE, typically JSON
 * @param {object} options     Additional context (sequenceNumber, etc.)
 */
async function handleSseEvent(eventName, dataString, options = {}) {
    const {
        sequenceNumber,
        messageType,
        guid,
        url,
        headers
    } = options;

    try {
        // If the server sends "[DONE]" literally (not JSON)
        if (dataString === '[DONE]') {
            console.log(`[${sequenceNumber}] [DONE] event received`);
            return;
        }

        // Attempt to parse JSON
        const eventObj = JSON.parse(dataString);
        console.log(`[${sequenceNumber}] [${eventName}]`, eventObj);

        // Example: handle streaming messages
        if (eventName === 'thread.message.delta' || eventName === 'thread.message.completed') {
            if (eventObj.delta && eventObj.delta.content &&
                eventObj.delta.content[0] && eventObj.delta.content[0].text &&
                eventObj.delta.content[0].text.value) {

                let message = eventObj.delta.content[0].text.value;

                // Example timestamp usage
                let timestamp = eventObj.delta.created_at ?? Date.now();
                let timestampUTC = new Date(timestamp).toISOString();
                let timestampLocal = convertToLocaleDateTime(timestampUTC);
                // ^ if created_at is in seconds, else remove *1000

                if (guid) {
                    // Append to UI
                    appendToContainer(messageType, guid, message, timestampLocal);
                }
            }

            if (eventName === 'thread.message.completed') {
                if (guid) {
                    save_message(guid, eventName, dataString, sequenceNumber);
                }
            }
        }
        else if (eventName === 'thread.run.completed') {
            // Run is fully done
            if (guid) {
                save_kissai_usage(guid, eventName, dataString, sequenceNumber);
            }
        }
        else if (eventName === 'thread.run.requires_action') {
            // Possibly we need to submit tool outputs
            if (eventObj.required_action && eventObj.required_action.submit_tool_outputs) {
                for (let toolCall of eventObj.required_action.submit_tool_outputs.tool_calls) {
                    if (toolCall.function.name === "get_current_time") {
                        // Provide current time in ISO format
                        let currentTime = new Date().toISOString();

                        // Send function response
                        await sendFunctionResponse(url, headers, eventObj.id, toolCall.id, {
                            current_time: currentTime
                        }, options);

                        console.log(`[${sequenceNumber}] Submitted function output for get_current_time`);

                        // If your API requires an explicit "restart" or "continue" call
                        // after submitting tool outputs, you do it here:
                        // console.log("Restarting run after function response...");
                        // await restartOpenAiRun(url, headers, eventObj.thread_id);
                    }
                }
            }
        }
        // ... handle other events as needed ...
    } catch (err) {
        console.warn(`Error handling SSE event "${eventName}" (#${sequenceNumber}):`, err);
    }
}

/*******************************************
 * 3) Sending Function Response
 *******************************************/

/**
 * POSTs the function output to the server.
 * Some servers might respond with SSE, or a short JSON/text response.
 *
 * @param {string} url          Base URL for the threads
 * @param {object} headers      Headers (including Authorization, etc.)
 * @param {string} runId        The run ID we're responding to
 * @param {string} toolCallId   The tool call ID we're responding to
 * @param {object} functionResponse  The data to pass as tool_outputs
 */
async function sendFunctionResponse(url, headers, runId, toolCallId, functionResponse, options = {}) {
    const responseBody = {
        tool_outputs: [
            {
                tool_call_id: toolCallId,
                output: JSON.stringify(functionResponse)
            }
        ],
        stream: true
    };

    try {
        // POST to submit_tool_outputs
        const response = await fetch(`${url}/${runId}/submit_tool_outputs`, {
            method: 'POST',
            headers: headers,
            body: JSON.stringify(responseBody)
        });

        // If the server *really does* return SSE here,
        // we can parse it with the same readSseStream
        await readSseStream(response, {
            onEvent: (eventName, dataString) => {
                options.sequenceNumber++;
                console.log(`[TOOL RESPONSE SSE] event=${eventName}`, dataString);
                handleSseEvent(eventName, dataString, options);
            },
            onDone: () => {
                console.log("Done reading SSE from submit_tool_outputs");
            }
        });

    } catch (error) {
        console.error("Error sending function response:", error);
    }
}

/*******************************************
 * 4) Main SSE Flow: fetchStreamWithPost
 *******************************************/

/**
 * Initiates an SSE connection via a POST request, then streams events,
 * calling handleSseEvent for each one.
 *
 * @param {string} url          SSE endpoint URL
 * @param {object} headers      Request headers
 * @param {object} body         POST body
 * @param {string} messageType  For your UI logic (e.g., 'sent' or 'rcvd')
 * @param {string} guid         A unique ID for the conversation, etc.
 */
async function fetchStreamWithPost(url, headers, body, messageType, guid) {
    try {
        // Start the SSE request
        const response = await fetch(url, {
            method: 'POST',
            headers,
            body: JSON.stringify(body),
        });

        // Initialize a sequenceNumber for each SSE event
        let sequenceNumber = 0;

        // Use the shared SSE parser
        await readSseStream(response, {
            onEvent: async (eventName, dataString) => {
                // Increment for each event
                sequenceNumber++;
                // Delegate to handleSseEvent
                await handleSseEvent(eventName, dataString, {
                    sequenceNumber,
                    messageType,
                    guid,
                    url,
                    headers
                });
            },
            onDone: () => {
                delete sseStreamStatus[guid];
                console.log('Main SSE stream completed');
            }
        });

        console.log('fetchStreamWithPost complete');
    } catch (error) {
        console.error('Error during fetchStreamWithPost:', error);
    }
}

function convertMarkdownRowIntoHtml(markdown, columnTag = 'td') {
    // Remove any trailing whitespace/newlines.
    let trimmed = markdown.trim();

    // Check that the string starts with "|" and ends with "|"
    if (trimmed[0] !== '|' || trimmed[trimmed.length - 1] !== '|') {
        throw new Error("Invalid markdown row format: must start and end with '|'");
    }

    // Remove the outer pipes.
    trimmed = trimmed.slice(1, -1);

    // Split the row by pipes.
    const cells = trimmed.split('|').map(cell => cell.trim());

    // Build the HTML row with <td> for each cell.
    const htmlCells = cells.map(cell => `<${columnTag}>${formatMarkupText(cell)}</${columnTag}>`).join('');
    return `<tr>${htmlCells}</tr>`;
}

function convertMarkdownTableToHtml(markdown) {
    const lines = markdown.split('<br>');
    let html = '';
    let tableBuffer = [];
    let inTable = false;
    let headerDetected = false;

    lines.forEach((line) => {
        if (line.includes('|')) {
            if (!inTable) {
                tableBuffer.push(line);
                inTable = true;
            } else if (!headerDetected && /^\|[-]+/.test(line.trim())) {
                headerDetected = true;
                tableBuffer.push(line);
            } else if (headerDetected) {
                tableBuffer.push(line);
            }
        } else if (inTable && line.trim() === '') {
            if (tableBuffer.length > 0) {
                html += parseTableBuffer(tableBuffer);
                tableBuffer = [];
                inTable = false;
                headerDetected = false;
            }
        } else {
            if (inTable) {
                tableBuffer.push(line);
            } else {
                html += line + '<br>';
            }
        }
    });

    if (tableBuffer.length > 0) {
        html += parseTableBuffer(tableBuffer);
    }

    return html;
}

function parseTableBuffer(tableBuffer) {
    let html = '<table class="msg-table"><thead>';

    const headers = tableBuffer[0].split('|').slice(1, -1).map(cell => cell.trim());
    html += '<tr>';
    html += `<th>${header}</th>`;
    headers.forEach(header => {
        html += `<th>${header}</th>`;
    });
    html += '</tr></thead><tbody>';

    tableBuffer.slice(2).forEach(rowLine => {
        const cells = rowLine.split('|').slice(1, -1).map(cell => cell.trim());
        html += '<tr>';
        cells.forEach(cell => {
            html += `<td>${cell}</td>`;
        });
        html += '</tr>';
    });

    html += '</tbody></table>';
    return html;
}

function formatMarkupText(text) {
    var newHtml = text.replace(/####\s*(.+?)\n/g, '<h4>$1</h4>');
    newHtml = newHtml.replace(/###\s*(.+?)\n/g, '<h3>$1</h3>');
    newHtml = newHtml.replace(/\*\*(.*?)\*\*/g, '<b>$1</b>');
    newHtml = newHtml.replace(/【.+?】/g, '');
    return newHtml;
}

/**
 * Replaces part of targetStr (from startPos inclusive to endPos exclusive)
 * with newStr. If positions are out of bounds or inverted, it adjusts them safely.
 *
 * @param {string} targetStr - The original string
 * @param {string} newStr - The replacement string
 * @param {number} startPos - The starting index for the replacement (inclusive)
 * @param {number} endPos - The ending index for the replacement (exclusive)
 * @returns {string} - The modified string
 */
function replaceText(targetStr, newStr, startPos, endPos) {
    // Ensure targetStr is actually a string
    if (typeof targetStr !== 'string') {
        throw new TypeError('replaceText: targetStr must be a string');
    }

    // Convert startPos and endPos to integers
    startPos = Math.max(0, Math.floor(startPos) || 0);
    endPos = Math.max(0, Math.floor(endPos) || 0);

    // If endPos < startPos, swap them
    if (endPos < startPos) {
        [startPos, endPos] = [endPos, startPos];
    }

    // Cap startPos and endPos within the string length
    if (startPos > targetStr.length) {
        // If startPos is beyond the string, append newStr at the end
        return targetStr + newStr;
    }
    if (endPos > targetStr.length) {
        endPos = targetStr.length;
    }

    const before = targetStr.slice(0, startPos);
    const after = targetStr.slice(endPos);

    return before + newStr + after;
}


function appendToContainer(messageType, guid, message, timestamp) {
    var className = (messageType === 'sent') ? 'msg-sent' : 'msg-rcvd';
    var containerSelector = "#kissai-response > ." + className + "[data-guid='" + guid + "']";
    var container = jQuery(containerSelector);
    var atts = getWidgetAtts('kissai-widget-container');
    var user_name = atts['user_name'] || "You";
    var ai_name = atts['ai_name'] || "KissAi";

    if (!sseStreamStatus[guid]) {
        sseStreamStatus[guid] = {
            status: 'text',  // or 'table', etc.
            buffer: '',
            pos: 0,
        };
    }

    if (container.length === 0) {
        container = jQuery('<div/>', {
            'class': className,
            'data-guid': guid
        }).appendTo('#kissai-response');

        var headerDiv = jQuery('<div/>', {
            'class': 'msg_header'
        }).appendTo(container);

        jQuery('<span/>', {
            'class': 'label_text',
            'text': (messageType === 'sent') ? user_name : ai_name
        }).appendTo(headerDiv);

        var timestampSpan = jQuery('<span/>', {
            'class': 'kissai-timestamp msg_time_stamp',
            'text': timestamp
        }).appendTo(headerDiv);

        updateElementTimestamp(timestampSpan);
    }

    let currentHtml = container.html();
    const borderPattern = /\|[|-]+\|\n/g;
    const borderMatch = borderPattern.exec(currentHtml);
    if (borderMatch) {
        const headerBorder = borderMatch.index;
        const dashedLine = borderMatch[0];
        const n_column_border = (dashedLine.match(/\|/g) || []).length;
        const prevPart = currentHtml.slice(0, headerBorder);
        let pipePositions = [];
        let pos = prevPart.indexOf("|");
        while (pos !== -1) {
            pipePositions.push(pos);
            pos = prevPart.indexOf("|", pos + 1);
        }
        const targetIndex = pipePositions[pipePositions.length - n_column_border];
        if (targetIndex !== undefined) {
            sseStreamStatus[guid].status = 'table';
            sseStreamStatus[guid].pos = targetIndex;

            // Remove the border line from container HTM
            const removeStart = borderMatch.index;
            const removeEnd = removeStart + borderMatch[0].length;
            container.html(function(index, oldHtml) {
                return replaceText(oldHtml, '', removeStart, removeEnd);
            });

            let endOfLine = currentHtml.indexOf('\n', targetIndex);
            container.html(function(index, oldHtml) {
                let html = '<table class="msg-table"><thead>';
                let htmlRow = convertMarkdownRowIntoHtml(currentHtml.substring(targetIndex, endOfLine), 'th');
                html += htmlRow;
                html += '</thead><tbody class="msg-table-body"></tbody></table>';
                return replaceText(oldHtml, html, targetIndex, endOfLine);
            });
        }
    }
    if (sseStreamStatus[guid].status === 'text') {
        message = message.replace(/\n/g, '\n<br>');
        container.html(function(index, oldHtml) {
            var newHtml = oldHtml + message;
            newHtml = formatMarkupText(newHtml);
            return newHtml;
        });
    }
    else if (sseStreamStatus[guid].status === 'table') {
        sseStreamStatus[guid].buffer += message;

        const rowRegex = /(\|[^\n]*\|\n)/;
        let match;
        while ((match = rowRegex.exec(sseStreamStatus[guid].buffer)) !== null) {
            const fullRow = match[0];
            // Remove it from the buffer
            sseStreamStatus[guid].buffer =
                sseStreamStatus[guid].buffer.slice(0, match.index) +
                sseStreamStatus[guid].buffer.slice(match.index + fullRow.length);

            // Convert row to HTML
            const rowHtml = convertMarkdownRowIntoHtml(fullRow);

            // Append to .msg-table-body
            container.find('.msg-table-body').append(rowHtml);
        }
    }
}


function save_message(guid, eventName, eventData, seq) {
    var form = this; // Correctly scoped reference to the form
    var kissai_widget_atts = get_kissai_widget_atts('kissai-widget-container');
    var formData = get_form_data(this, { 'action' : 'kissai_save_message_ajax',
        'seq': seq,
        'guid': guid, // Unique identifier for the message stream
        'event': eventName,
        'kissai_widget_atts': kissai_widget_atts,
        'data': eventData, // Number of retry attempt (handling communication error)
        'nonce' : kissai_vars.nonce } );

    jQuery.ajax({
        type: 'POST',
        url: kissai_vars.ajax_url, // Provided by WordPress
        dataType: 'json', // Expect JSON response
        context: form,
        data: formData,
        success: function(response) {
            if (response.hasOwnProperty('data')) {
                var responseData = response.data;
                // Check if the response has the expected properties
                if (responseData.hasOwnProperty('guid') && responseData.hasOwnProperty('seq')) {
                } else {
                    console.log('Received an unexpected response format. Full response:', response);
                }
                // Check for and execute script if present
                process_script_from_response(responseData);
            } else {
                // This will log the full response if the expected 'data' property is missing as well
                console.log('Response format is not as expected. Full response:', response);
            }
        },
        error: function(xhr, status, error) {
            setTimeout(() => append_message(guid, seq, 1), 5000);
            console.log('An error occurred: ', error);
        }
    });
}

function save_kissai_usage(guid, eventName, eventData, seq) {
    var form = this; // Correctly scoped reference to the form
    var kissai_widget_atts = get_kissai_widget_atts('kissai-widget-container');
    var formData = get_form_data(this, { 'action' : 'kissai_save_usage_ajax',
        'seq': seq,
        'guid': guid, // Unique identifier for the message stream
        'event': eventName,
        'kissai_widget_atts': kissai_widget_atts,
        'data': eventData, // Number of retry attempt (handling communication error)
        'nonce' : kissai_vars.nonce } );

    jQuery.ajax({
        type: 'POST',
        url: kissai_vars.ajax_url, // Provided by WordPress
        dataType: 'json', // Expect JSON response
        context: form,
        data: formData,
        success: function(response) {
            if (response.hasOwnProperty('data')) {
                var responseData = response.data;
                // Check if the response has the expected properties
                if (responseData.hasOwnProperty('guid') && responseData.hasOwnProperty('seq')) {
                } else {
                    console.log('Received an unexpected response format. Full response:', response);
                }
                // Check for and execute script if present
                process_script_from_response(responseData);
            } else {
                // This will log the full response if the expected 'data' property is missing as well
                console.log('Response format is not as expected. Full response:', response);
            }
        },
        error: function(xhr, status, error) {
            console.log('An error occurred: ', error);
        }
    });
}
function save_api_log(endpoint, event, message, data) {
    var formData = { 'action' : 'kissai_save_api_log_ajax',
        'endpoint': endpoint,
        'event': event,
        'message': message,
        'data': data, // Number of retry attempt (handling communication error)
        'nonce' : kissai_vars.nonce };

    jQuery.ajax({
        type: 'POST',
        url: kissai_vars.ajax_url, // Provided by WordPress
        dataType: 'json', // Expect JSON response
        data: formData,
        success: function(response) {
            if (response.hasOwnProperty('data')) {
                var responseData = response.data;
                // Check for and execute script if present
                process_script_from_response(responseData);
            } else {
                // This will log the full response if the expected 'data' property is missing as well
                console.log('Response format is not as expected. Full response:', response);
            }
        },
        error: function(xhr, status, error) {
            setTimeout(() => append_message(guid, seq, atmt), 5000);
            console.log('An error occurred: ', error);
        }
    });
}

function append_message(guid, seq, atmt) {
    var form = this; // Correctly scoped reference to the form
    var formData = get_form_data(this, { 'action' : 'kissai_fetch_delta_ajax', 'seq': seq, 'guid': guid, 'atmt': atmt, 'nonce' : kissai_vars.nonce } );

    console.log('append_message("' + guid + '", ' + seq + ', ' + atmt + ') is called');

    $('#loading-animation').show();
    jQuery.ajax({
        type: 'POST',
        url: kissai_vars.ajax_url, // Provided by WordPress
        dataType: 'json', // Expect JSON response
        context: form,
        data: formData,
        success: function(response) {
            if (response.hasOwnProperty('data')) {
                var responseData = response.data;
                // Check if the response has the expected properties
                if (responseData.hasOwnProperty('message_type') && responseData.hasOwnProperty('message') && responseData.hasOwnProperty('guid')) {
                    // Use the new function to append messages
                    appendToContainer(responseData.message_type, responseData.guid, responseData.message, responseData.created_at);
                    append_message(guid, responseData.seq + 1, 0); // Continue fetching the next part of the message stream
                } else if(responseData.hasOwnProperty('end_of_stream')) {
                    if (responseData.hasOwnProperty('message'))
                        console.log('End of message stream reached.' + response.message);
                    else
                        console.log('End of message stream reached.');
                    return; // Stop the recursion if no more messages are available
                } else {
                    console.log('Received an unexpected response format. Full response:', response);
                }
                // Check for and execute script if present
                process_script_from_response(responseData);
            } else {
                // This will log the full response if the expected 'data' property is missing as well
                console.log('Response format is not as expected. Full response:', response);
            }
        },
        error: function(xhr, status, error) {
            setTimeout(() => append_message(guid, seq, atmt), 5000);
            console.log('An error occurred: ', error);
        }
    });
}

function refresh_admin() {
    var form = this; // Correctly scoped reference to the form
    var formData = { 'action' : 'kissai_ajax_admin_refresh',
        'nonce' : kissai_vars.nonce };

    jQuery.ajax({
        type: 'POST',
        url: kissai_vars.ajax_url, // Provided by WordPress
        dataType: 'json', // Expect JSON response
        context: form,
        data: formData,
        success: function(response) {
            handle_success_refresh_admin_response(form, response);
        },
        error: function(xhr, status, error) {
            console.log('An error occurred: ', error);
        }
    });
}


function refresh_file_list() {
    var kissai_widget_atts = get_kissai_widget_atts('kissai-widget-container');
    var form = this; // Correctly scoped reference to the form
    var formData = { 'action' : 'kissai_ajax_admin_file_list_refresh',
        'kissai_widget_atts' : kissai_widget_atts,
        'nonce' : kissai_vars.nonce };

    jQuery.ajax({
        type: 'POST',
        url: kissai_vars.ajax_url, // Provided by WordPress
        dataType: 'json', // Expect JSON response
        data: formData,
        beforeSend: function() {
            $("#kissai-admin-widget-container .spinner-animation").show();
        },
        success: function(response) {
            $("#kissai-admin-widget-container .spinner-animation").hide();
            handle_success_file_list_refresh_admin_response(form, response);
        },
        error: function(xhr, status, error) {
            console.log('An error occurred: ', error);
        }
    });
}

function checkFetchAndStreamSupport() {
    var retVal = true;
    if ('fetch' in window) {
        console.log('Fetch API is supported.');
    } else {
        retVal = false;
        console.log('Fetch API is not supported.');
        // You might want to implement a fallback or display a message to the user
    }

    if ('ReadableStream' in window) {
        console.log('ReadableStream is supported.');
    } else {
        retVal = false;
        console.log('ReadableStream is not supported.');
        // Implement a fallback or inform the user about the lack of support
    }
    return retVal;
}

function autoResizeTextarea() {
    this.style.height = 'auto'; // Reset height - allows shrink if deleting text
    this.style.height = (this.scrollHeight) + 'px'; // Set to scroll height
}

function kissai_widget_loading_show(show) {
    var $ = jQuery;
    if (show) {
        $('#kissai-widget-container .loading-animation-container').show();
        $('#kissai-widget-container .loading-animation-container .loading-animation').show();
    }
    else {
        $('#kissai-widget-container .loading-animation-container').hide();
        $('#kissai-widget-container .loading-animation-container .loading-animation').hide();
    }
}
jQuery(function($) {
    var textarea = $(this).find('textarea[name="kissai_prompt"]');

    // Bind the auto-resize function to the input event of the textarea
    textarea.on('input', autoResizeTextarea);

    // Initialize textarea size on page load
    textarea.each(autoResizeTextarea);

    $('#kissai-form').submit(function(event) {
        event.preventDefault(); // Prevent the form from submitting via the browser.

        var process_from = checkFetchAndStreamSupport() ? 'local' : 'server';
        var form = this; // Correctly scoped reference to the form
        var formData = get_form_data(this, { 'action' : 'kissai_ask_ajax',
         'process_from': process_from,
         'nonce' : kissai_vars.nonce } );

        textarea.val('');
        textarea.val('').css('height', 'auto');

        $.ajax({
            type: 'POST',
            url: kissai_vars.ajax_url, // This is dynacognitive declinemically provided by WordPress
            dataType: 'json', // Expect a JSON response
            context: form,
            data: formData,
            beforeSend: function() {
                kissai_widget_loading_show(true);
            },
            success: function(response) {
                // Check if the response has the expected properties
                if (response.hasOwnProperty('data')) {
                    responseData = response.data;
                    if (responseData.hasOwnProperty('message_type') && responseData.hasOwnProperty('message') && responseData.hasOwnProperty('guid')) {
                        // Use the new function to append messages
                        appendToContainer(responseData.message_type, responseData.guid, responseData.message, responseData.created_at);
                        if (responseData.hasOwnProperty('fetch_url') && responseData.hasOwnProperty('fetch_headers') && responseData.hasOwnProperty('fetch_body') && responseData.hasOwnProperty('fetch_message_type')) {
                            fetchStreamWithPost(responseData.fetch_url, responseData.fetch_headers, responseData.fetch_body, responseData.fetch_message_type, responseData.guid)
                            .then(result => {
                                console.log('fetchStreamWithPost() Stream completed:', result);
                                kissai_widget_loading_show(false);
                            })
                            .catch(error => {
                                console.error('fetchStreamWithPost() Error during streaming:', error);
                                kissai_widget_loading_show(false);
                            });
                        }
                        else {
                            setTimeout(function() {
                                append_message(responseData.guid, 1, 0);
                            }, 5000); // Continue fetching the next part of the message stream
                        }
                    } else {
                        // Handle unexpected response format
                        console.log('Received an unexpected response format. Full response:' + JSON.stringify(response));
                    }
                    // Check for and execute script if present
                    process_script_from_response(responseData);
                }
            },
            error: function(xhr, status, error) {
                // Handle AJAX error
                kissai_widget_loading_show(false);
                $('#kissai-response').html('An error occurred while processing your request.');
            }
        });
    });
    
    textarea.keydown(function(e) {
        // Enter key without Shift
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault(); // Prevent default Enter behavior (newline)

            // Manually trigger form submission
            $('#kissai-form').submit();
        }
        // If Shift + Enter is pressed, allow the default behavior (new line)
    });
});

function get_form_data(formElement, additionalData) {
    var formData = jQuery(formElement).serializeArray();
    var mergedData = {};
    jQuery.each(formData, function(i, field) {
        mergedData[field.name] = field.value;
    });
    var mergedData = Object.assign({}, mergedData, additionalData);
    return mergedData;
}

function init_training_buttons() {
    init_file_upload_elements();
    init_text_upload_elements();
    init_delete_file_buttons();
    init_update_instructions_button();
    init_download_elements();
    init_edit_file_elements();
    init_suggested_question_elements();
}

function handle_success_refresh_admin_response(form, response, target_selector = '#kissai-widget-container') {
    var $ = jQuery; // Define shorthand locally
    var response_area = $('#kissai-admin-widget-container .admin-widget-response');

    if (response.success) {
        // Assuming the server sends new HTML to be rendered
        $(target_selector).html(response.data.html);

        init_training_buttons();

        if (response.data) {
            process_script_from_response(response.data);
            if (response.data.message) {
                response_area.html(response.data.message);
            }
        }
    } else {
        response_area.html('<p>' + response.data.message + '</p>');
    }
}

function handle_success_file_list_refresh_admin_response(form, response) {
    var $ = jQuery; // Define shorthand locally
    var response_area = $('#kissai-admin-widget-container .admin-widget-response');

    if (response.success) {
        // Assuming the server sends new HTML to be rendered
        $('#kissai-file-list-container').html(response.data.html);
        init_training_buttons();

        if (response.data) {
            process_script_from_response(response.data);
        }
    } else {
        response_area.html('<p>' + response.data.message + '</p>');
    }
}

jQuery(function($) {
    $('#kissai-login-form').submit(function(e) {
        e.preventDefault();
        var form = this; // Correctly scoped reference to the form
        var formData = get_form_data(this, { 'action' : 'kissai_ajax_login', 'nonce' : kissai_vars.nonce } );
        $.ajax({
            type: 'POST',
            dataType: 'json',
            url: kissai_vars.ajax_url, // Ensure ajaxurl is defined globally or passed to the script
            data: formData,
            context: form,
            beforeSend: function() {
                // Optionally, you can also clear or manipulate .login-response here
                $(this).find('.login-response').empty(); // Use the closure correctly if needed, see note below
            },
            success: function(response) {
                handle_success_refresh_admin_response(form, response);
            }
        });
    });
});

function init_delete_file_buttons() {
    var $ = jQuery;
    var response_area = $('#kissai-admin-widget-container .admin-widget-response');
    $('.kissai-file-list').on('click', '.delete-file', function(e) {
        e.preventDefault();
        var fileId = $(this).data('file-id');  // Get the file ID from data attribute

        var kissai_widget_atts = get_kissai_widget_atts('kissai-widget-container');
        response_area.html('');
        $.ajax({
            type: 'POST',
            url: kissai_vars.ajax_url,  // Ensure ajaxurl is defined globally or passed to the script
            data: {
                'action': 'kissai_delete_file_ajax', // WP AJAX action hook
                'file_id': fileId,  // Pass file ID to server
                'kissai_widget_atts': kissai_widget_atts,
                'nonce': kissai_vars.nonce  // Passing the security nonce
            },
            beforeSend: function() {
                response_area.html("Loading...");
            },
            success: function(response) {
                if (response.success) {
                    response_area.html('<p>File deleted successfully</p>');
                    // Optionally, remove the file entry from the list
                    $('.kissai-file-list li[data-file-id="' + fileId + '"]').remove();
                } else {
                    response_area.html('<p>' + 'Error: ' + response.data.message + '</p>');
                }
            },
            error: function(xhr, status, error) {
                alert('Failed to delete the file.');
            }
        });
    });
}
jQuery(init_delete_file_buttons);

function update_vector_store(url, http_headers, file_id, guid) {
    var $ = jQuery; // Define shorthand locally
    var response_area = $('#kissai-admin-widget-container .admin-widget-response');

    http_headers['Content-Type'] = 'application/json';

    const jsonBody = JSON.stringify({ 'file_id': file_id });

    fetch(url, {
        method: 'POST',
        headers: http_headers,
        body: jsonBody, // Attach the FormData object
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok: ' + 'code: (' + response.status + ') ' + getStatusMessage(response.status));
        }
        return response.json(); // Make sure to return the JSON processing promise
    })
    .then(data => {
        console.log('Success:', data);
        if (data.hasOwnProperty('id') && data.hasOwnProperty('status')) {
            const jsonString = JSON.stringify(data);
            save_api_log(url, data.object, 'success', jsonString);
            refresh_file_list();
        }
    })
    .catch(error => {
        console.error('Error:', error);
        response_area.html('<p>' + 'Error: ' + error.message + '</p>');
    });
}

function upload_file_to_openai(assistant_id, url, http_headers, guid, create_vector_store_url, file, filename = null, formData = null) {
    var $ = jQuery; // Define shorthand locally
    var response_area = $('#kissai-admin-widget-container .admin-widget-response');

    if (formData == null) {
        formData = new FormData();
        formData.set('purpose', 'assistants');
        if (filename != null)
            formData.set('file', file, filename);
        else {
            formData.set('file', file);
            filename = file.name;
        }
    }
    else {
        if (filename == null) {
            filename = file.name;
        }
    }
    return new Promise((resolve, reject) => {
        fetch(url, {
            method: 'POST',
            headers: http_headers,
            body: formData, // Attach the FormData object
            // Note: Fetch will set the Content-Type to 'multipart/form-data' automatically when you provide FormData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + 'code: (' + response.status + ') ' + getStatusMessage(response.status));
            }
            if (!response.body) {
                console.error('No response body' + response.body);
                return;
            }
            const reader = response.body.getReader(); // Assuming the server responds with JSON
            let chunks = []; // Array to hold received chunks
            let textDecoder = new TextDecoder();

            function readChunk() {
                return reader.read().then(({ done, value }) => {
                    if (done) {
                        return; // Stream is complete
                    }
                    chunks.push(textDecoder.decode(value, {stream: true})); // Decode chunk as UTF-8 and add to chunks array
                    return readChunk(); // Read next chunk
                });
            }

            return readChunk().then(() => {
                return chunks.join(''); // Combine all chunks to form the final data
            });
        })
        .then(jsonString => {
            const responseData = JSON.parse(jsonString);
            if (responseData.hasOwnProperty('id')) {
                const file_id = responseData.id;
                save_api_log(url, 'file.uploaded', 'success', jsonString);
                update_vector_store(create_vector_store_url, http_headers, file_id, guid);
                if (filename == null) {
                    filename = responseData.filename;
                }
                update_knowledge_file_id(assistant_id, filename, file_id);
                console.log('Success:', responseData);
                response_area.html('<p>File uploaded successfully!</p>');
                resolve(true);
            } else {
                reject('No ID found in response');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error uploading file: ' + error.message);
            reject(error);
        });
    });
}

function upload_file_to_kissai(url, http_headers, user_id, assistant_id, file, filename = null, formData = null) {
    var $ = jQuery; // Define shorthand locally
    if (formData == null) {
        formData = new FormData();
        formData.set('user_id', user_id);
        formData.set('assistant_id', assistant_id);
        if (filename != null)
            formData.set('file', file, filename);
        else
            formData.set('file', file);
    }
    return new Promise((resolve, reject) => {
        fetch(url, {
            method: 'POST',
            headers: http_headers,
            body: formData, // Attach the FormData object
            // Note: Fetch will set the Content-Type to 'multipart/form-data' automatically when you provide FormData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok: ' + 'code: (' + response.status + ') ' + getStatusMessage(response.status));
            }
            if (!response.body) {
                console.error('No response body' + response.body);
                return;
            }
            const reader = response.body.getReader(); // Assuming the server responds with JSON
            let chunks = []; // Array to hold received chunks
            let textDecoder = new TextDecoder();

            function readChunk() {
                return reader.read().then(({ done, value }) => {
                    if (done) {
                        return; // Stream is complete
                    }
                    chunks.push(textDecoder.decode(value, {stream: true})); // Decode chunk as UTF-8 and add to chunks array
                    return readChunk(); // Read next chunk
                });
            }

            return readChunk().then(() => {
                return chunks.join(''); // Combine all chunks to form the final data
            });
        })
        .then(jsonString => {
            const responseData = JSON.parse(jsonString);
            if (responseData.hasOwnProperty('id')) {
                const file_id = responseData.id;
                console.log('Success:', responseData);
                resolve(true);
            } else {
                reject('No ID found in response');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            reject(error);
        });
    });
}

function update_assistant_instruction(url, http_headers, instructions) {
    var $ = jQuery; // Define shorthand locally
    var response_area = $('#kissai-admin-widget-container .admin-widget-response');

    const jsonBody = JSON.stringify({ 'instructions': instructions });

    fetch(url, {
        method: 'POST',
        headers: http_headers,
        body: jsonBody, // Attach the FormData object
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok: ' + 'code: (' + response.status + ') ' + getStatusMessage(response.status));
        }
        return response.json(); // Make sure to return the JSON processing promise
    })
    .then(data => {
        console.log('Success:', data);
        if (data.hasOwnProperty('id')) {
            const jsonString = JSON.stringify(data);
            save_api_log(url, data.object, 'success', jsonString);
            response_area.html('<p>' + 'Instruction is updated' + '</p>');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        response_area.html('<p>' + 'Error: ' + error.message + '</p>');
    });
}

// Check if the object is a plain JavaScript object
function isPlainObject(obj) {
    return typeof obj === 'object' && obj !== null && 
           !Array.isArray(obj) && !(obj instanceof Date) && 
           !(obj instanceof Function);
}

function add_to_FormData(formData, additionalData = {}) {
    if (isPlainObject(additionalData)) {
        for (const key in additionalData) {
            if (additionalData.hasOwnProperty(key)) {
                formData.append(key, additionalData[key]);
            }
        }
    }
    return formData;
}

// Function to get form data from a form element and optionally merge additional fields
function get_FormData(additionalData = {}, form = null) {
    let formData;
    if (form) {
        formData = new FormData(form);
    } else {
        formData = new FormData();
    }
    formData = add_to_FormData(formData, additionalData);
    return formData;
}

function update_knowledge_file_id(assistant_id, file_name, file_id) {
    var kissai_widget_atts = get_kissai_widget_atts('kissai-widget-container');
    var formData = new FormData();
    var form = document.getElementById('file-upload-form');
    formData = add_to_FormData(formData, {
        'action': 'kissai_update_knowledge_file_id', // Action must match the server-side
        'kissai_widget_atts': kissai_widget_atts,
        'nonce': kissai_vars.nonce, // Security nonce
        'assistant_id': assistant_id, // The actual file to be uploaded
        'file_name': file_name,
        'file_id': file_id
    });

    // if checkFetchAndStreamSupport() returns 'server' it means fetch is not available so $.ajax() has to be used
    $.ajax({
        type: 'POST',
        url: kissai_vars.ajax_url,
        data: formData,
        context: form,
        processData: false,
        contentType: false,
        success: function(response) {
            if (response.success) {
            } else {
                console.log('Update knowledge file_id failed: ', response.data.message);
            }
        },
        error: function(xhr, status, error) {
            console.log('Update knowledge file_id failed. %s', error);
        }
    });
}

function upload_file_to_website(file, filename = null, file_id = null) {
    var kissai_widget_atts = get_kissai_widget_atts('kissai-widget-container');
    var form = document.getElementById('file-upload-form');
    var formData = new FormData();
    formData = add_to_FormData(formData, {
        'action': 'kissai_handle_file_upload', // Action must match the server-side
        'file': file, // The actual file to be uploaded
        'kissai_widget_atts': kissai_widget_atts,
        'nonce': kissai_vars.nonce, // Security nonce
        'process_from': 'server'
    });

    if (filename !== null) {
        formData.append('filename', filename);
    }

    if (file_id !== null) {
        formData.append('file_id', file_id);
    }

    // if checkFetchAndStreamSupport() returns 'server' it means fetch is not available so $.ajax() has to be used
    return $.ajax({
        type: 'POST',
        url: kissai_vars.ajax_url,
        data: formData,
        context: form,
        processData: false,
        contentType: false,
        beforeSend: function() {
            $("#kissai-admin-widget-container .spinner-animation").show();
        }
    }).done(function(response) {
        $("#kissai-admin-widget-container .spinner-animation").hide();
        if (response.success) {
            console.log('File uploaded to website successfully');
        } else {
            console.log('Error: ' + response.data.message);
        }
    }).fail(function(xhr, status, error) {
        $("#kissai-admin-widget-container .spinner-animation").hide();
        console.log('Failed to upload the file to website. ' + error);
    });
}

function uploadFile(file) {
    var $ = jQuery; // Define shorthand locally
    var form = document.getElementById('file-upload-form');
    var formData = new FormData();
    var kissai_widget_atts = get_kissai_widget_atts('kissai-widget-container');
    var response_area = $('#kissai-admin-widget-container .admin-widget-response');
    var filelist_response = $('#kissai-admin-widget-container .kissai-file-list-update-response');

    response_area.html('');
    $.ajax({
        type: 'POST',
        url: kissai_vars.ajax_url,
        data: {
            'action': 'kissai_handle_file_upload', // WP AJAX action hook
            'process_from': 'local',
            'kissai_widget_atts': kissai_widget_atts,
            'nonce': kissai_vars.nonce  // Passing the security nonce
        },
        dataType: 'json', // Expect a JSON response
        context: form,
        beforeSend: function() {
            $("#kissai-admin-widget-container .spinner-animation").show();
            filelist_response.html('');
        },
        success: function(response) {
            $("#kissai-admin-widget-container .spinner-animation").hide();
            if (response.success) {
                if (response.hasOwnProperty('data')) {
                    responseData = response.data;
                    if (responseData.hasOwnProperty('fetch_url') && responseData.hasOwnProperty('fetch_headers')) {
                        formData.set('purpose', 'assistants');
                        formData.set('file', file);
                        upload_file_to_website(file);
                        upload_file_to_openai(responseData.assistant_id,
                            responseData.fetch_url,
                            responseData.fetch_headers,
                            responseData.guid,
                            responseData.create_vector_store_url,
                            file,
                            null,
                            formData
                        );
                    }
                    else if (typeof responseData === 'string') {
                        filelist_response.html('<p>' + responseData + '</p>');
                    }
                }
            } else {
                if (response.hasOwnProperty('data')) {
                    responseData = response.data;
                    if (typeof responseData === 'string') {
                        filelist_response.html('<p>' + responseData + '</p>');
                    }
                    else if (responseData.hasOwnProperty('message')) {
                        filelist_response.html('<p>' + responseData.message + '</p>');
                    }
                }
                else {
                    alert('Error: ' + response.data.message);
                }
            }
        },
        error: function(xhr, status, error) {
            $("#kissai-admin-widget-container .spinner-animation").hide();
            alert('Failed to upload the file.');
        }
    });
}

function init_file_upload_elements() {
    var $ = jQuery; // Define shorthand locally
    var dropArea = $('.file-drop-area');

    // Remove existing event handlers if already assigned
    dropArea.off('dragenter dragover dragleave drop');

    // Prevent default drag behaviors
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dropArea.on(eventName, preventDefaults, false);
    });

    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    // Highlight drop area when item is dragged over it
    ['dragenter', 'dragover'].forEach(eventName => {
        dropArea.on(eventName, () => dropArea.addClass('hover'));
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dropArea.on(eventName, () => dropArea.removeClass('hover'));
    });

    // Handle dropped files
    dropArea.on('drop', handleDrop);

    function handleDrop(e) {
        var dt = e.originalEvent.dataTransfer;
        var files = dt.files;

        handleFiles(files);
    }

    // Update file when it is selected via the input
    $('#file-upload').off('change').on('change', function() {
        handleFiles(this.files);
    });

    function handleFiles(files) {
        ([...files]).forEach(uploadFile);
    }
}

jQuery(init_file_upload_elements);

function init_update_instructions_button() {
    var $ = jQuery;
    var response_area = $('#kissai-admin-widget-container .admin-widget-response');

    let textarea = $('textarea[name="kissai_admin_instructions"]');

    textarea.on('input', autoResizeTextarea);

    // Initialize textarea size on page load
    textarea.each(autoResizeTextarea);

    $('#kissai-admin-instructions-form').submit(function(e) {
        e.preventDefault();
        var kissai_widget_atts = get_kissai_widget_atts('kissai-widget-container');
        var form = this; // Correctly scoped reference to the form
        var formData = get_form_data(this, {
            'action' : 'kissai_ajax_admin_instruction_update',
            'kissai_widget_atts': kissai_widget_atts,
            'nonce' : kissai_vars.nonce } );

        $.ajax({
            type: 'POST',
            dataType: 'json',
            url: kissai_vars.ajax_url, // Ensure ajaxurl is defined globally or passed to the script
            data: formData,
            context: form,
            beforeSend: function() {
                // Optionally, you can also clear or manipulate .login-response here
                response_area.empty(); // Use the closure correctly if needed, see note below
            },
            success: function(response) {
                responseData = response.data;
                if (responseData.hasOwnProperty('fetch_url') && responseData.hasOwnProperty('fetch_headers')) {
                    update_assistant_instruction(responseData.fetch_url, responseData.fetch_headers, textarea.val());
                }
            }
        });
    });
}
jQuery(init_update_instructions_button);

function init_text_upload_elements() {
    var $ = jQuery; // Define shorthand locally
    var response_area = $('#kissai-admin-widget-container .admin-widget-response');
    let textarea = $('textarea[name="kissai_text_upload"]');

    // Initialize textarea size on page load
    textarea.each(autoResizeTextarea).on('input', autoResizeTextarea);


    $('#kissai-text-upload-form')
        .off('submit')
        .on('submit', function(e) {
        e.preventDefault();
        let kissai_text_file_id = $('#kissai-text-upload-form input[name="kissai_text_file_id"]');
        let filenameInput = $('#kissai-text-upload-form input[name="kissai_text_upload_name"]');
        let file_name = filenameInput.val();
        let text = textarea.val();
        let file_id = kissai_text_file_id.val();
        if (text === "") {
            textarea.addClass("red-placeholder");
            return;
        }
        if (file_name === "") {
            file_name = text.split("\n")[0].trim();
            file_name = file_name.replace(/[^a-zA-Z0-9 ]/g, "");
            file_name = file_name.substring(0, 80);
            filenameInput.val(file_name);
        }
        if (file_name !== "") {
            file_name += ".txt";
        }
    
        var kissai_widget_atts = get_kissai_widget_atts('kissai-widget-container');
        var form = this; // Correctly scoped reference to the form
        var formData = get_form_data(this, {
            'action' : 'kissai_handle_file_upload',
            'process_from': 'local',
            'filename' : file_name,
            'kissai_widget_atts': kissai_widget_atts,
            'nonce' : kissai_vars.nonce
        });

        if (file_id !== "") {
            formData['file_id'] = file_id;
        }

        $.ajax({
            type: 'POST',
            dataType: 'json',
            url: kissai_vars.ajax_url, // Ensure ajaxurl is defined globally or passed to the script
            data: formData,
            context: form,
            beforeSend: function() {
                // Optionally, you can also clear or manipulate .login-response here
                filenameInput.removeClass("red-placeholder");
                textarea.removeClass("red-placeholder");
                kissai_text_file_id.val('');
                response_area.empty(); // Use the closure correctly if needed, see note below
            },
            success: function(response) {
                responseData = response.data;
                if (responseData.hasOwnProperty('fetch_url') && responseData.hasOwnProperty('fetch_headers')) {
                    var file = new Blob([text], {type: 'text/plain'});
                    if (file_id === '') {
                        file_id = null;
                    }
                    upload_file_to_website(file, file_name)
                        .then(function(response) {
                            // response here is what the AJAX call returned
                            if (response && response.success) {
                                // Now call OpenAI
                                upload_file_to_openai(
                                    responseData.assistant_id,
                                    responseData.fetch_url,
                                    responseData.fetch_headers,
                                    responseData.guid,
                                    responseData.create_vector_store_url,
                                    file,
                                    file_name
                                ).then(success => {
                                    if (success) {
                                        filenameInput.val('');
                                        textarea.val('');
                                        textarea.each(autoResizeTextarea);
                                    }
                                }).catch(error => {
                                    console.log('Upload failed: ', error);
                                });
                            }
                        });
                }
                else {
                    response_area.html('<p class="notice notice-error">' + responseData + '</p>');
                }
            }
        });
    });
}
jQuery(init_text_upload_elements);


function download_knowledge(file_id) {
    var $ = jQuery;
    var kissai_widget_atts = get_kissai_widget_atts('kissai-widget-container');
    $.ajax({
        url: kissai_vars.ajax_url, // This variable should be predefined in WordPress admin using wp_localize_script
        type: 'POST',
        data: {
            action: 'kissai_handle_file_download', // This should match the action hook suffix in add_action
            nonce: kissai_vars.nonce,
            kissai_widget_atts: kissai_widget_atts,
            file_id: file_id
        },
        success: function(response) {
            responseData = response.data;
            if (responseData.hasOwnProperty('message')) {
                alert(responseData.message);
            }
            else if (responseData.hasOwnProperty('url')) {
                var link = document.createElement('a');
                link.href = responseData.url;
                link.target = '_blank';
                link.download = ''; // This can be omitted if you do not want to force download
    
                // Append to the body to ensure visibility to the DOM
                document.body.appendChild(link);
                
                // Trigger the click event
                link.click();
    
                // Remove the link after triggering to clean up
                document.body.removeChild(link);
            }
            else {
                alert('Error communicating with server');
            }
        },
        error: function(response) {
            alert('Error: ' + response.message); // Alert error message
        }
    });
}

function init_download_elements() {
    var $ = jQuery; // Define shorthand locally
    var response_area = $('#kissai-admin-widget-container .admin-widget-response');
    var textarea = $('#kissai-text-upload-form textarea[name="kissai_text_upload"]'); // Ensure correct selector
    var downloads = $('.kissai-file-list .download-file'); // Ensure correct global selector if needed

    downloads.each(function() {
        $(this).on('click', function(e) {
            e.preventDefault();
            var file_id = $(this).data('file-id'); // Retrieve the file ID stored in data attribute
            download_knowledge(file_id); // Call the function to handle the download
        });
    });
}

jQuery(init_download_elements);

function edit_knowledge(file_id, file_name) {
    var $ = jQuery;
    var kissai_widget_atts = get_kissai_widget_atts('kissai-widget-container');
    $.ajax({
        url: kissai_vars.ajax_url, // This variable should be predefined in WordPress admin using wp_localize_script
        type: 'POST',
        data: {
            action: 'kissai_handle_file_edit', // This should match the action hook suffix in add_action
            nonce: kissai_vars.nonce,
            kissai_widget_atts: kissai_widget_atts,
            file_id: file_id,
            file_name: file_name
        },
        success: function(response) {
            if (response.success && response.data) {
                var responseData = response.data;
                // Find the input element for the file name and fill it
                $('#kissai-text-upload-form input[name="kissai_text_file_id"]').val(responseData.file_id);
                $('#kissai-text-upload-form input[name="kissai_text_upload_name"]').val(responseData.title);
                // Find the textarea for the file content and fill it
                let textarea = $('#kissai-text-upload-form textarea[name="kissai_text_upload"]');
                textarea.val(responseData.body);
                textarea.each(autoResizeTextarea);
                $('html, body').animate({
                    scrollTop: $('#kissai-admin-widget-container .widget-divider').offset().top
                }, 1000);
            } else {
                alert('Error: Unable to fetch file details.');
            }
        },
        error: function(response) {
            // Handling JSON or network errors
            var message = response.responseJSON && response.responseJSON.message ? response.responseJSON.message : 'Failed to communicate with server';
            alert('Error: ' + message);
        }
    });
}

function init_edit_file_elements() {
    var $ = jQuery; // Define shorthand locally
    var response_area = $('#kissai-admin-widget-container .admin-widget-response');
    var textarea = $('#kissai-text-upload-form textarea[name="kissai_text_upload"]'); // Ensure correct selector
    var edits = $('.kissai-file-list .edit-file'); // Ensure correct global selector if needed

    edits.each(function() {
        $(this).on('click', function(e) {
            e.preventDefault();
            var file_id = $(this).data('file-id'); // Retrieve the file ID stored in data attribute
            var file_name = $(this).data('file-name'); // Retrieve the file ID stored in data attribute
            edit_knowledge(file_id, file_name); // Call the function to handle the download
        });
    });
}

jQuery(init_edit_file_elements);

function downloadFile(url, filename) {
    $('#kissai-progress-container').show();
    fetch(url)
        .then(response => {
            const contentLength = response.headers.get('Content-Length');
            if (!contentLength) {
                throw new Error('Content-Length header is missing');
            }

            const total = parseInt(contentLength, 10);
            let loaded = 0;

            const reader = response.body.getReader();
            const stream = new ReadableStream({
                start(controller) {
                    function push() {
                        reader.read().then(({ done, value }) => {
                            if (done) {
                                controller.close();
                                return;
                            }
                            loaded += value.length;
                            updateProgress(loaded, total);
                            controller.enqueue(value);
                            push();
                        });
                    }
                    push();
                }
            });

            return new Response(stream);
        })
        .then(response => response.blob())
        .then(blob => {
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            link.href = url;
            link.download = filename;

            link.dispatchEvent(new MouseEvent('click', {
                bubbles: true,
                cancelable: true,
                view: window
            }));

            URL.revokeObjectURL(url);
            $('#kissai-progress-container').hide();
        })
        .catch(error => {
            console.error('Error downloading the file:', error);
            $('#kissai-progress-container').hide();
        });
}

function updateProgress(loaded, total) {
    const percent = Math.round((loaded / total) * 100);
    $('#kissai-progress-bar').css('width', percent + '%');
    $('#kissai-progress-bar').text(percent + '%');
}

jQuery(document).ready(function($) {
    $('#verify_email_btn').on('click', function() {
        var email = $('input[name="email"]').val();
        var firstName = $('input[name="first_name"]').val();
        var lastName = $('input[name="last_name"]').val();
        if (email) {
            $.ajax({
                url: kissai_vars.ajax_url,
                type: 'POST',
                data: {
                    'action': 'kissai_save_email_ajax',
                    'email': email,
                    'first_name': firstName,
                    'last_name': lastName,
                    'nonce': kissai_vars.nonce,
                },
                success: function(response) {
                    if (response.hasOwnProperty('data')) {
                        var responseData = response.data;
                        var baseUrl = responseData.endpoint;
                        var currentUrl = encodeURIComponent(window.location.href);
                        var queryParams = "first_name=" + encodeURIComponent(firstName) + 
                                        "&last_name=" + encodeURIComponent(lastName) + 
                                        "&email=" + encodeURIComponent(email) +
                                        "&ret_url=" + currentUrl;
                        var fullUrl = baseUrl + "?" + queryParams;
                        window.location.href = fullUrl;
                    }
                    else {
                        $('#verification_status').html(response);
                    }
                },
                error: function(xhr, status, error) {
                    $('#verification_status').text('Server error. Please try again.');
                }
            });
        } else {
            $('#verification_status').text('Please enter a valid email.');
        }
    });
});

jQuery(document).ready(function($) {
    $('.train-assistant').on('click', function(e) {
        e.preventDefault(); // Prevent the default action for the event, if needed
        var assistantId = $(this).data('assistant-id');
        var form = $(this).closest('form'); // Get the closest form containing this select
        var formData = get_form_data(this, {
            'action' : 'kissai_ajax_admin_widget_refresh',
            'nonce' : kissai_vars.nonce,
            'assistant_id' : assistantId
        } );

        $.ajax({
            type: 'POST',
            dataType: 'json',
            url: kissai_vars.ajax_url, // Ensure ajaxurl is defined globally or passed to the script
            data: formData,
            context: form,
            beforeSend: function() {
                // Optionally, you can also clear or manipulate .login-response here
                var $spinner = $('#admin-kissai-widget-container .spinner-animation').detach();
                $('#admin-kissai-widget-container').html("<div style='text-align: center;'>Loading...</div>");
                $('#admin-kissai-widget-container').append($spinner);
                $spinner.show();
                $(this).find('#admin-kissai-widget-container-response').empty(); // Use the closure correctly if needed, see note below
            },
            success: function(response) {
                handle_success_refresh_admin_response(form, response, '#admin-kissai-widget-container');
            }
        });
    });
});

function init_register_user_button() {
    var $ = jQuery;
    $('#kissai_register_form').submit(function(e) {
        e.preventDefault(); // Prevent the default action for the event, if needed
        var form = $(this).closest('form'); // Get the closest form containing this select
        var formData = get_form_data(this, { 'action' : 'kissai_ajax_update_user_details', 'nonce' : kissai_vars.nonce } );

        $.ajax({
            type: 'POST',
            dataType: 'json',
            url: kissai_vars.ajax_url, // Ensure ajaxurl is defined globally or passed to the script
            data: formData,
            context: form,
            beforeSend: function() {
                // Optionally, you can also clear or manipulate .login-response here
                $('#register_response').html("Registering...");
            },
            success: function(response) {
                if (response.success) {
                    responseData = response.data;
                    if (responseData.hasOwnProperty('message')) {
                        $('#register_response').html(responseData.message);
                    }
                }
                else
                    $('#register_response').html(response.data);

            },
            error: function(response) {
                $('#register_response').html(response.data);
            }
        });
    });
}
jQuery(init_register_user_button);

function init_delete_chat_button() {
    var $ = jQuery;
    $('#kissai-widget-container .kissai-widget-button-container a.delete-chat').on('click', function(e) {
        e.preventDefault(); // Prevent the default action for the event, if needed
        if (confirm('Are you sure you want to delete this conversation? This cannot be undone.')) {
            var form = $('#kissai-widget-container form');
            var formData = get_form_data(form, {
                'action' : 'kissai_ajax_delete_chat',
                'nonce' : kissai_vars.nonce
            });

            $.ajax({
                type: 'POST',
                dataType: 'json',
                url: kissai_vars.ajax_url,
                data: formData,
                context: form,
                beforeSend: function() {
                    kissai_widget_loading_show(true);
                },
                success: function(response) {
                    kissai_widget_loading_show(false);
                    if (response.success) {
                        responseData = response.data;
                        if (responseData.hasOwnProperty('message')) {
                            $('#kissai-response').html(responseData.message);
                        }
                        else {
                            $('#kissai-response').html('');
                        }
                    }
                    else
                        $('#kissai-response').html(response.data);
                },
                error: function(response) {
                    $('#kissai-response').html(response.data);
                    kissai_widget_loading_show(false);
                }
            });
        }
    });
}
jQuery(init_delete_chat_button);

function init_query_sample_button() {
    var $ = jQuery;
    // When any button within the 'suggested-questions' div is clicked
    $('.suggested-questions .query-sample').on('click', function(e) {
        e.preventDefault(); // Prevent default button click behavior
        
        // Get the text inside the clicked button
        var promptText = $(this).text();
        
        // Set the text to the textarea with the name 'kissai_prompt'
        $('#kissai-widget-container textarea[name="kissai_prompt"]').val(promptText);

        // Trigger the submit button click for the form 'kissai-form'
        $('#kissai-widget-container form button[type="submit"]').click();
    });
}
jQuery(init_query_sample_button);

function init_load_query_sample_button() {
    var $ = jQuery;
    // When any button within the 'suggested-questions' div is clicked
    $('.suggested-questions .load-query-sample').on('click', function(e) {
        e.preventDefault(); // Prevent default button click behavior
        load_suggested_questions();
    });
}
jQuery(init_load_query_sample_button);

function extractJsonContent(inputString) {
    const startTag = "```json";
    const endTag = "```";

    // Check if the input string contains the specified start tag
    let startIndex = inputString.indexOf(startTag);
    if (startIndex >= 0) {
        startIndex += startTag.length;
        // Find the position of the closing tag
        const endIndex = inputString.indexOf(endTag, startIndex);
        // Extract the content between the tags
        if (endIndex > startIndex) {
            const jsonContent = inputString.substring(startIndex, endIndex).trim();
            return jsonContent;
        }
    }

    // If not found or invalid format, return an empty string
    return "";
}

function updateQuerySample(jsonData) {
    var $ = jQuery;
    let output = '';

    var atts = getWidgetAtts('kissai-widget-container');
    var q_label = atts['suggested_questions_label'];
    if (q_label !== null && q_label !== '') {
        q_label = `<span>${q_label}</span>`;
    }
    else {
        q_label = '';
    }

    jsonData.forEach(item => {
        if (item.question) {
            if (atts['suggested_questions_style'] === "Button") {
                output += `<button class="query-sample button">${q_label}${item.question}</button>`;
            }
            else {
                output += `<a class="query-sample">${q_label}${item.question}</a>`;
            }
        }
    });

    // Using jQuery to update the HTML of the button container
    $('#kissai-widget-container .suggested-questions-body').html(output);
}
function processQuerySample(message) {
    var $ = jQuery;
    const messageData = JSON.parse(message); // Assuming `message` is a JSON string
    const json = extractJsonContent(messageData.content[0].text.value);
    const jsonData = JSON.parse(json);

    if (jsonData) {
        updateQuerySample(jsonData);
    }
}


function load_suggested_questions() {
    var $ = jQuery;
    var form = $('#kissai-form'); // Correctly scoped reference to the form
    var kissai_widget_atts = get_kissai_widget_atts('kissai-widget-container');
    var formData = get_form_data(form, { 'action' : 'kissai_ajax_suggested_questions',
        'kissai_widget_atts': kissai_widget_atts,
        'nonce' : kissai_vars.nonce } );

    $('#kissai-widget-container .suggested-questions .loading-animation').show();

    $.ajax({
        type: 'POST',
        url: kissai_vars.ajax_url, // This is dynacognitive declinemically provided by WordPress
        dataType: 'json', // Expect a JSON response
        context: form,
        data: formData,
        success: function(response) {
            // Check if the response has the expected properties
            if (response.success) {
                if (response.hasOwnProperty('data')) {
                    responseData = response.data;
                    if (responseData.hasOwnProperty('count') && responseData.hasOwnProperty('questions') && responseData.count > 0) {
                        updateQuerySample(responseData.questions);
                        $('#kissai-widget-container .suggested-questions .loading-animation').hide();
                        $('#kissai-widget-container .suggested-questions .suggested-questions-body').append(responseData.reload_button);
                        init_query_sample_button();
                        init_load_query_sample_button();
                        $('.suggested-questions-header').show();
                    }
                    else if (responseData.hasOwnProperty('message_type') && responseData.hasOwnProperty('message')) {
                        if (responseData.hasOwnProperty('fetch_url') && responseData.hasOwnProperty('fetch_headers') && responseData.hasOwnProperty('fetch_body') && responseData.hasOwnProperty('fetch_message_type')) {
                            fetchStreamWithPost(responseData.fetch_url, responseData.fetch_headers, responseData.fetch_body, responseData.fetch_message_type, null)
                            .then(result => {
                                processQuerySample(result[0].data);
                                $('#kissai-widget-container .suggested-questions .loading-animation').hide();
                                $('#kissai-widget-container .suggested-questions .suggested-questions-body').append(responseData.reload_button);
                                init_query_sample_button();
                                init_load_query_sample_button();
                                $('.suggested-questions-header').show();
                                console.log('fetchStreamWithPost() Stream completed:', result);
                            })
                            .catch(error => {
                                console.error('fetchStreamWithPost() Error during streaming:', error);
                                $('#kissai-widget-container .suggested-questions .loading-animation').hide();
                            });
                        }
                        else {
                            // Display a message if communication with the site plugin fails
                            $('#kissai-widget-container .suggested-questions .loading-animation').hide   ();
                        }
                    } else {
                        // Handle unexpected response format
                        $('#kissai-widget-container .suggested-questions .loading-animation').hide();
                        console.log('Received an unexpected response format. Full response:' . response);
                    }
                    // Check for and execute script if present
                    process_script_from_response(responseData);
                }
            }
            else {
                $('#kissai-widget-container .suggested-questions .loading-animation').hide();
                $('#kissai-widget-container .suggested-questions-body').html(response.data);
            }
        },
        error: function(xhr, status, error) {
            // Handle AJAX error
            console.log('An error occurred while processing your request: ' + error);
            $('#kissai-widget-container .suggested-questions .loading-animation').hide();
        }
    });

}
function init_suggested_question_elements() {
    var $ = jQuery;
    var $assistant_id = $('#kissai_assistant_id');
    var assistantId   = $assistant_id.val();
    var nonce = kissai_vars.nonce; // Nonce for security checks
    var questions =     [];      // We'll load them via AJAX
    var selectedIndex = -1;  // Track which row is selected, -1 means none

    // ---------------------------------------------------------------------
    // 1) Fetch existing questions from server
    // ---------------------------------------------------------------------
    function loadQuestions() {
        $.ajax({
            url:  kissai_vars.ajax_url,
            method: 'POST',
            data: {
                action: 'kissai_get_suggested_questions',
                nonce:  nonce,
                assistant_id: assistantId
            },
            success: function(response) {
                if(response.success) {
                    const rawQuestions = response.data.questions || [];
                    questions = rawQuestions.map(item => {
                        if (typeof item === 'string') {
                            return item;
                        }
                        else if (item && typeof item === 'object' && 'question' in item) {
                            return item.question;
                        }
                        return '';
                    });
                    renderTable();
                } else {
                    alert('Failed to load questions: ' + (response.data || 'Unknown error'));
                }
            },
            error: function(err) {
                alert('AJAX error loading questions: ' + err.statusText);
            }
        });
    }

    // ---------------------------------------------------------------------
    // 2) Render questions table
    // ---------------------------------------------------------------------
    function renderTable() {
        var $tbody = $('#kissai-questions-table tbody');
        $tbody.empty(); // Clear existing rows

        selectedIndex = -1;             // Reset selection
        $('#kissai-btn-update').hide(); // Hide "Edit" button
        $('#kissai-btn-add').show();    // Always show "Add"
        $('#kissai-question-input').val(''); // Clear input

        // If there are no questions, display a single row with a message
        if (questions.length === 0) {
            $tbody.append(
                '<tr><td colspan="3" style="text-align:center;">No saved suggested questions found.</td></tr>'
            );
            return;
        }

        // Build rows

        for (var i = 0; i < questions.length; i++) {
            var question = questions[i];
            if (question.hasOwnProperty('question')) {
                question = question.question;
            }
            var safeQuestion = $('<div/>').text(question).html(); // Escape for safety

            var rowHtml =
                '<tr data-index="'+ i +'">' +
                // 1) Checkbox for multi-select
                '<td>' +
                '<input type="checkbox" class="kissai-select-question" data-index="'+ i +'" />' +
                '</td>' +
                // 2) Question text
                '<td class="kissai-question-text">'+ safeQuestion +'</td>' +
                // 3) Single delete button (optional)
                '<td><button class="kissai-btn-delete" data-index="'+ i +'">Delete</button></td>' +
                '</tr>';

            $tbody.append(rowHtml);
        }
    }

    // ---------------------------------------------------------------------
    // 3) Save updated questions (full list) to server
    // ---------------------------------------------------------------------
    function saveQuestions(merge = true) {
        $.ajax({
            url: kissai_vars.ajax_url,
            method: 'POST',
            data: {
                action: 'kissai_save_suggested_questions',
                nonce: nonce,
                assistant_id: assistantId,
                merge_mode: merge,
                // We'll send the entire updated array as JSON string
                questions: JSON.stringify(questions)
            },
            success: function(response) {
                if(response.success) {
                    // Reload after saving
                    loadQuestions();
                } else {
                    alert('Failed to save questions: ' + (response.data || 'Unknown error'));
                }
            },
            error: function(err) {
                alert('AJAX error saving questions: ' + err.statusText);
            }
        });
    }

    // ---------------------------------------------------------------------
    // 4) Add new question
    // ---------------------------------------------------------------------
    $('#kissai-btn-add').click(function(e) {
        e.preventDefault();
        var newQ = $('#kissai-question-input').val().trim();
        if (newQ === '') {
            alert('Please enter a question.');
            return;
        }
        // Add it to local array
        questions.push(newQ);
        // Immediately save to server (merge = true)
        saveQuestions(true);
    });

    // ---------------------------------------------------------------------
    // 5) Edit selected question
    // ---------------------------------------------------------------------
    $('#kissai-btn-update').click(function(e) {
        e.preventDefault();
        if (selectedIndex < 0 || selectedIndex >= questions.length) {
            alert('No question is selected to edit.');
            return;
        }
        var editedQ = $('#kissai-question-input').val().trim();
        if (editedQ === '') {
            alert('Please enter a question.');
            return;
        }
        questions[selectedIndex] = editedQ;
        // For editing, we set merge to false, if you want to replace existing
        saveQuestions(false);
    });

    // ---------------------------------------------------------------------
    // 6) Delete (single row)
    // ---------------------------------------------------------------------
    $(document).on('click', '.kissai-btn-delete', function(e) {
        e.preventDefault();
        var idx = parseInt($(this).attr('data-index'), 10);
        if (confirm('Are you sure you want to delete this question?')) {
            // Remove from array
            questions.splice(idx, 1);
            saveQuestions(false);
        }
    });

    // ---------------------------------------------------------------------
    // 7) Table Row Click (Select single item for editing)
    // ---------------------------------------------------------------------
    $(document).on('click', '#kissai-questions-table tbody tr', function(e) {
        // If the user clicked the checkbox or delete button, we don't want to override that
        if ($(e.target).is('input[type="checkbox"]') || $(e.target).hasClass('kissai-btn-delete')) {
            return;
        }

        var idx = parseInt($(this).attr('data-index'), 10);
        if (idx < 0 || idx >= questions.length) return;

        // Mark this row as selected
        selectedIndex = idx;
        $('#kissai-question-input').val(questions[idx]);

        // Show 'Update' button
        $('#kissai-btn-update').show();
        // 'Add' remains visible
        $('#kissai-btn-add').show();

        // Highlight the selected row visually (optional)
        $(this).addClass('selected').siblings().removeClass('selected');
    });

    // ---------------------------------------------------------------------
    // 8) Select All / Deselect All
    // ---------------------------------------------------------------------
    $('#kissai-btn-select-all').click(function(e) {
        e.preventDefault();

        var $checkBoxes = $('#kissai-questions-table tbody').find('.kissai-select-question');
        if ($checkBoxes.length === 0) return;

        // Check if we need to select all or deselect all
        var allChecked = true;
        $checkBoxes.each(function() {
            if (!$(this).prop('checked')) {
                allChecked = false;
                return false; // break
            }
        });
        // If allChecked is false, we check them all; otherwise we uncheck them.
        $checkBoxes.prop('checked', !allChecked);
    });

    // ---------------------------------------------------------------------
    // 9) Delete Selected
    // ---------------------------------------------------------------------
    $('#kissai-btn-delete-selected').click(function(e) {
        e.preventDefault();

        var selectedIndices = [];
        $('.kissai-select-question:checked').each(function() {
            var idx = parseInt($(this).attr('data-index'), 10);
            if (!isNaN(idx)) {
                selectedIndices.push(idx);
            }
        });

        if (selectedIndices.length === 0) {
            alert('No questions selected to delete.');
            return;
        }

        if (!confirm('Are you sure you want to delete the selected questions?')) {
            return;
        }

        // Remove them from the array in descending order so we don't break the index references
        selectedIndices.sort(function(a, b){ return b - a; });
        for (var i = 0; i < selectedIndices.length; i++) {
            questions.splice(selectedIndices[i], 1);
        }

        // Save & reload (merge = false)
        saveQuestions(false);
    });

    // ---------------------------------------------------------------------
    // Load the questions on page load
    // ---------------------------------------------------------------------
    loadQuestions();
}
