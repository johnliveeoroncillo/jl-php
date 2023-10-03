const categories = {
    GRAMMAR: 'bg-yellow-600',
    TYPOS: 'bg-red-600',
    CASING: 'bg-blue-600',
    PUNCTUATION: 'bg-yellow-600',
}

function buildUrl() {
    return 'https://api.languagetoolplus.com/v2/check?c=1&instanceId=user%3A2056483&v=standalone';
}

function parseErrorMessage(type, message) {
    if (type === "CASING") {
        return "Capitalize the first word";
    }

    return message;
}

function addToList(payload) {
    const container = $('#correction-container');

    const errorMessage = parseErrorMessage(payload.errorType, payload.errorMessage);
    container.append(`
        <li class="correction-words border rounded-full px-2 py-1 flex flex-row gap-2 items-center hover:bg-gray-100 cursor-pointer" data-json='${JSON.stringify(payload)}'>
            <span class="w-3 h-3 rounded-full ${categories[payload.errorType]}"></span>
            <span>${payload.text.value}</span>
            <span class="text-xs">${errorMessage}</span>
        </li>
    `);
}

function clearList() {
    const container = $('#correction-container');
    container.html('');
}

function loader(element) {
    if ($(element).hasClass('loading')) {
        $(element).removeClass('loading');
    } else {
        $(element).addClass('loading');
    }

}

function parseData(payload) {
    clearList();
    const { matches } = payload;
    if (matches.length > 0) {
        for (let i = 0; i < matches.length; i += 1) {
            const match = matches[i];
            const { rule, length, offset, replacements, shortMessage } = match;
            const { category } = rule;

            addToList({
                errorType: category.id,
                errorMessage: shortMessage,
                text: replacements[0],
                length,
                offset
            })
        }
    }
}

async function checkGrammar(string) {
    await $.ajax({
        url: buildUrl(),
        type: 'post',
        async: true,
        data: {
            language: 'en-US',
            tokenV2: '1c454d4c6846ffe3e0abee071d89f834',
            username: 'johnliveeoroncillo@gmail.com',
            data: JSON.stringify({text:string}),
        },
        success(data) {
            parseData(data);
        },
        error(err) {
            console.error(err);
        }
    })
}


function replaceWords(payload) {
    const writeArea = $('#write-area');

    //{errortype":"casing","errormessage":"","text":{"value":"how"},"length":3,"offset":0}"
    const value = writeArea.val().trim();
    console.log(value, payload.text.value);
    const split = value.split('');
    console.log(split);
    split.splice(payload.offset, payload.length, payload.text.value);
    console.log(split);

    writeArea.val(split.join(''));
}

$(document).ready(function() {
    const writeArea = $('#write-area');

    $('#check-grammar').on('click', async function () {
        const value = writeArea.val();

        if (value.trim() == '') {
            return;
        }

        loader($(this));
        await checkGrammar(value.trim());
        loader($(this));
    });

    $('body').on('click', '.correction-words', function() {
        const data = $(this).data('json');
        replaceWords(data);
        $(this).remove();
        checkGrammar(writeArea.val().trim());
    });

    $('#write-area').on('change', function() {
        const length = $(this).val().length;
        $('#length-container').html(length);
    }); 
});