export async function createAgent(deps) {

    const {
        restUrl,
        nonce,
        z,
        Annotation,
        StateGraph,
        MessagesAnnotation,
        ToolNode,
        toolsCondition,
        ChatPromptTemplate,
        HumanMessage,
        AIMessage,
        uuidv4,
        SessionStorageCheckpointer,
        llmService,
        setPath,
        updateDataModel,
        createSurface,
        deleteSurface,
        updateComponents,
        widgetStore,
        activeSurfaceId,
        agentState,
        basicFunctions,

        // ---------------------------------------------------------
        // LOKALNE NARZĘDZIA
        // ---------------------------------------------------------
        tools = [],

        // ---------------------------------------------------------
        // SEMANTYCZNE WYSZUKIWANIE NARZĘDZI WORDPRESS
        // ---------------------------------------------------------
        getExternalTools

    } = deps;


    /* ============================================================
       1. PROMPT
    ============================================================ */

    const assistantPrompt = ChatPromptTemplate.fromMessages([
        [
            "system",
            `
Jesteś wirtualnym asystentem panelu administracyjnego serwisu elektronicznego SQS Serwis.

Language: Polish
Current time: {time}

Masz dostęp do narzędzi lokalnych oraz narzędzi WordPress
wybranych automatycznie na podstawie zapytania użytkownika.

Używaj dostępnych narzędzi, jeżeli są potrzebne do wykonania zadania.

Nie zakładaj, że narzędzie istnieje, jeżeli nie zostało przekazane
w bieżącym kontekście.
`
        ],
        ["placeholder", "{messages}"],
    ]);


    /* ============================================================
       2. STATE
    ============================================================ */

    const AgentState = Annotation.Root({

        ...MessagesAnnotation.spec,

        /*
         * Narzędzia wybrane przez semantic search
         */
        activeTools: Annotation({
            reducer: (_, y) => y,
            default: () => []
        }),

        /*
         * Zapytanie, dla którego wykonano ostatni semantic search.
         *
         * Dzięki temu po wykonaniu narzędzia nie wykonujemy
         * ponownie wyszukiwania dla tego samego pytania.
         */
        semanticQuery: Annotation({
            reducer: (_, y) => y,
            default: () => ""
        }),

    });


    /* ============================================================
       3. HELPER - OSTATNIA WIADOMOŚĆ UŻYTKOWNIKA
    ============================================================ */

    const getLastHumanMessage = (messages) => {

        if (!messages?.length) {
            return null;
        }

        return [...messages]
            .reverse()
            .find(message => {

                if (message instanceof HumanMessage) {
                    return true;
                }

                /*
                 * Dodatkowo obsługa sytuacji, gdy wiadomość
                 * nie jest instancją HumanMessage, ale ma role.
                 */
                return message?.role === "user";

            }) || null;
    };


    /* ============================================================
       4. EXTRACT QUERY
    ============================================================ */

    const getMessageText = (message) => {

        if (!message) {
            return "";
        }

        const content = message.content;

        if (typeof content === "string") {
            return content.trim();
        }

        /*
         * LangChain może czasami zwracać content jako tablicę bloków.
         */
        if (Array.isArray(content)) {

            return content
                .map(item => {

                    if (typeof item === "string") {
                        return item;
                    }

                    if (
                        item &&
                        typeof item === "object" &&
                        typeof item.text === "string"
                    ) {
                        return item.text;
                    }

                    return "";
                })
                .join(" ")
                .trim();
        }

        return String(content ?? "").trim();
    };


    /* ============================================================
       5. LOAD SEMANTICALLY SELECTED TOOLS
    ============================================================ */

    const loadSelectedTools = async (state) => {

        const lastHumanMessage = getLastHumanMessage(state.messages);

        if (!lastHumanMessage) {
            return {};
        }

        const query = getMessageText(lastHumanMessage);

        if (!query) {
            return {};
        }

        /*
         * ---------------------------------------------------------
         * NIE WYKONUJ PONOWNIE SEARCH DLA TEGO SAMEGO USER QUERY
         * ---------------------------------------------------------
         */

        if (state.semanticQuery === query) {
            return {};
        }

        console.log("[Agent] Semantic tool search:", query);

      
        /* ========================================================
           SEMANTIC SEARCH
        ======================================================== */

        let externalTools = [];

        try {

            externalTools = await getExternalTools(null, query);

        } catch (error) {
            console.error("[Agent] getExternalTools() failed:", error);
            externalTools = [];
        }

        if (!Array.isArray(externalTools)) {
            externalTools = [];
        }

        console.log("[Agent] Semantic tools:",  externalTools.map(tool => tool?.name));

        /*
         * Możesz tutaj np. ograniczyć wynik do 8 najlepiej
         * dopasowanych narzędzi.
         *
         * Zakładam, że backend już zwraca właściwie posortowane
         * narzędzia.
         */
        externalTools = externalTools
            .filter(Boolean)
            .slice(0, 8);
       
        /*
         * activeTools przechowuje WYŁĄCZNIE narzędzia z semantic
         * search.
         *
         * Lokalne tools dokładamy później w callAssistant /
         * executeTools.
         */
        return {
            activeTools: externalTools,
            semanticQuery: query
        };
    };


  


    /* ============================================================
       6. ASSISTANT
    ============================================================ */

    const callAssistant = async (state, config) => {

        const llm = llmService.get("chatgpt-4o-mini");

        /*
         * Narzędzia dostępne dla LLM:
         *
         * 1. lokalne tools
         * 2. semantycznie znalezione WordPress Ability
         */
        const allTools = [
            ...tools,
            ...state.activeTools
        ];

        console.log("[Agent] Tools bound to LLM:", allTools.map(tool => tool.name));

        const chain = assistantPrompt.pipe(
            llm.bindTools(allTools)
        );

        const response = await chain.invoke({
            time: new Date().toISOString(),
            messages: state.messages
        });

        return {
            messages: [ response ]
        };
    };
  


    /* ============================================================
       7. EXECUTE TOOLS
    ============================================================ */

    const executeTools = async (state) => {

       /*
        * Dokładnie ten sam zestaw tools, który został przekazany
        * do LLM.
        */
        const allTools = [
            ...tools,
            ...state.activeTools
        ];
      
        console.log("[Agent] Executing tools:", allTools.map(tool => tool.name));

        const toolNode = new ToolNode(allTools);

        return await toolNode.invoke(state);
    };


    /* ============================================================
       8. GRAPH
    ============================================================ */

    const workflow = new StateGraph(AgentState)
       // START
       .addNode("load_tools", loadSelectedTools)
       // LLM
       .addNode("assistant", callAssistant)
       // TOOL EXECUTION
       .addNode("tools", executeTools)

       /*
        * --------------------------------------------------------
        * USER MESSAGE
        * --------------------------------------------------------
        *
        * Najpierw semantic search.
        */
        .addEdge("__start__", "load_tools")

         // semantic search -> LLM
        .addEdge("load_tools", "assistant")
      /*
        * LLM:
        *
        * - brak tool call -> END
        * - tool call    -> tools
        */
        .addConditionalEdges("assistant", toolsCondition)


       /*
        * Po wykonaniu narzędzia wracamy bezpośrednio do LLM.
        *
        * NIE robimy tutaj ponownie semantic search.
        */
        .addEdge("tools", "assistant");



    /* ============================================================
       9. COMPILE
    ============================================================ */
    const memory = new SessionStorageCheckpointer();

    return workflow.compile({
        checkpointer: memory
    });

}


