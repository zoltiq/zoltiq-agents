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
Jesteś asystentem administratora strony panelu administracyjnego WordPress serwisu elektronicznego SQS Serwis.

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


        needExternalTools: Annotation({
          reducer: (_, y) => y,
          default: () => false
      })

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
       5. ASSISTANT
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
       6. ROUTER
    ============================================================ */
    const routerSchema = z.object({

      need_external_tools: z
          .boolean()
          .describe(
              "Czy do odpowiedzi potrzebne jest narzędzie WordPress spoza lokalnych tools."
          ),
  
      query: z
          .string()
          .describe(
              "Zwięzły opis operacji, którą należy wykonać za pomocą narzędzia. " +
              "Uwzględnij kontekst całej rozmowy. " +
              "Jeżeli narzędzia zewnętrzne nie są potrzebne, zwróć pusty string."
          )
  
    });


  const routeTools = async (state) => {

    const llm = llmService.get("router");

    const routerPrompt = ChatPromptTemplate.fromMessages([
        [
            "system",
            `
Jesteś routerem narzędzi dla agenta administracyjnego WordPress.

Analizuj CAŁĄ dotychczasową rozmowę, a nie tylko ostatnią wiadomość użytkownika.

Twoim zadaniem jest ustalić:

1. Czy do odpowiedzi potrzebne jest zewnętrzne narzędzie WordPress
   wyszukiwane semantycznie.
2. Jeżeli tak, przygotuj krótkie i precyzyjne zapytanie semantyczne,
   opisujące rzeczywistą operację potrzebną w tym momencie.

NIE wybieraj narzędzia po nazwie.
NIE wymyślaj nazw Ability.

Nie uruchamiaj wyszukiwania dla:
- powitań,
- small talk,
- pytań ogólnych,
- sytuacji, w których można odpowiedzieć bez narzędzi.

Jeżeli potrzebne jest narzędzie WordPress, query powinno opisywać
konkretną operację, np.:

"pobranie listy zainstalowanych wtyczek WordPress"

albo:

"pobranie szczegółów zamówienia WooCommerce o numerze 123"

Uwzględniaj wcześniejszy kontekst rozmowy.

Przykład:

Użytkownik:
"Sprawdź zamówienia WooCommerce."

Asystent:
"Jakie?"

Użytkownik:
"Te z ostatnich 7 dni."

Wtedy query powinno opisywać:
"pobranie zamówień WooCommerce z ostatnich 7 dni"

a nie tylko:
"ostatnie 7 dni".
`
        ],
        ["placeholder", "{messages}"]
    ]);


    const router = routerPrompt
        .pipe(
            llm.withStructuredOutput(routerSchema)
        );


    const decision = await router.invoke({
        messages: state.messages
    });

    console.log("[Agent] Tool router decision:",  decision);

    if (!decision.need_external_tools) {

        return {
            needExternalTools: false,
            semanticQuery: "",
            activeTools: []
        };
    }

    const query = decision.query?.trim();

    if (!query) {
        return {
            needExternalTools: false,
            semanticQuery: "",
            activeTools: []
        };
    }


   /*
    * Dopiero teraz wykonujemy semantic search.
    */

    let externalTools = [];

    try {
        externalTools = await getExternalTools(null, query);
    } catch (error) {
        console.error("[Agent] getExternalTools failed:", error);
        externalTools = [];
    }

    if (!Array.isArray(externalTools)) {
        externalTools = [];
    }

   /*
    * Ograniczamy liczbę narzędzi przekazywanych do modelu.
    */
    externalTools = externalTools
        .filter(Boolean)
        .slice(0, 8);

    console.log("[Agent] Selected external tools:",  externalTools.map(tool => tool?.name));

    return {
        needExternalTools: true,
        semanticQuery: query,
        activeTools: externalTools
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
       .addNode("route_tools", routeTools)
       
       // LLM
       .addNode("assistant", callAssistant)
       
       // TOOL EXECUTION
       .addNode("tools", executeTools)

      /*
       * --------------------------------------------------------
       * USER MESSAGE
       * --------------------------------------------------------
       */
      
       // Każdą nową wiadomość najpierw analizuje router LLM.
       .addEdge("__start__", "route_tools")

       // Router -> assistant
       .addEdge("route_tools", "assistant")
      
       // Assistant -> ToolNode albo END
       .addConditionalEdges("assistant", toolsCondition)

       // Po wykonaniu narzędzia wracamy bezpośrednio do LLM.
      .addEdge("tools", "assistant");



    /* ============================================================
       9. COMPILE
    ============================================================ */
    const memory = new SessionStorageCheckpointer();

    return workflow.compile({
        checkpointer: memory
    });

}