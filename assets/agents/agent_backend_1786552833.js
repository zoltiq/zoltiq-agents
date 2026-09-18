export async function createAgent(deps) {
	const { 
      restUrl, nonce, z, Annotation, StateGraph, MessagesAnnotation, 
      tool, getExternalTools, ToolNode, toolsCondition, ToolMessage,
      ChatPromptTemplate, HumanMessage, AIMessage, uuidv4,
      SessionStorageCheckpointer, llmService, on, actions,
      setPath, updateDataModel, createSurface, deleteSurface, updateComponents,
      widgetStore, activeSurfaceId, agentState, basicFunctions
	} = deps;


   /* ============================================================
	   1. PROMPT
	============================================================ */
   const assistantPrompt = ChatPromptTemplate.fromMessages([
			["system",
`Jesteś wirtualnym asystentem panelu administracyjnego serwisu elektronicznego SQS Serwis.

Language messages: Poland   
Current time: {time}`
],
["placeholder", "{messages}"],
]);


   
  /* ============================================================
	   2. STATE
	============================================================ */
	const AgentState = Annotation.Root({
		...MessagesAnnotation.spec,

        activeToolGroup: Annotation({
          reducer: (_, y) => y,
          default: () => null
        }),

        activeTools: Annotation({
          reducer: (_, y) => y,
          default: () => []
        }),

        stop_after_tool: Annotation({
            reducer: (_, y) => y,
            default: () => false
        })

	});


   /* ============================================================
	   3. NODES
	============================================================ */

const detectToolGroup = async(messages)=>{

    const llm = llmService.get("router");

    const response = await llm.invoke([
        {
            role:"system",
            content:`
Jesteś routerem narzędzi.

Twoim zadaniem jest wybrać jedną grupę narzędzi w WordPress,
która najlepiej pasuje do pytania użytkownika.

Dostępne grupy:

admin-plugin:
Narzędzia związane z konfiguracją wtyczek.
Używaj gdy użytkownik pyta o:
- ustawienia wtyczki włącz / wyłącz
- listę zainstalowanych wtyczek
- aktulizację wtyczek 

admin-user:
Narzędzia związane z zarządzaniem użytkownikami WordPress.
Używaj gdy użytkownik pyta o:
- użytkowników
- role
- uprawnienia
- profile
- konta

admin-theme:
Narzędzia związane z konfiguracją motywów.
Używaj gdy użytkownik pyta o:
- przełączenie na inny motyw
- listę zainstalowanych motywów
- aktualizację motywów

admin-post:
Narzędzia związane z obsługą postów.
Używaj gdy użytkownik pyta o:
- pobranie listy postów lub pojedyńczego postu
- utworzenie nowego postu
- aktualacji postu
- usunięcia postu

admin-page:
Narzędzia związane z obsługą stron.
Używaj gdy użytkownik pyta o:
- pobranie listy stron lub pojedyńczej strony
- utworzenie nowej strony
- aktualacji strony
- usunięcia postu


admin-search:
Narzędzia związane wyszukiwaniem postów, wpisów.
Używaj gdy użytkownik pyta o:
- faq, wpisy blogów, dokumenty

Zwróć tylko nazwy grup.
            `
        },
        {
            role:"user",
            content: messages.at(-1).content
        }
    ]);

    return response.content.trim();
};

  
 const loadTools = async (state) => {
 
      const group = await detectToolGroup(state.messages);
  
      const tools = await getExternalTools(group);
  
      return {
          activeToolGroup: group,
          activeTools: tools
      };
  };

	

   /* ---------- ASSISTANT NODE ---------- */
	const callAssistant = async (state, config) => {

        const tools = state.activeTools;

        const llm = llmService.get("agent");
		
		const chain = assistantPrompt.pipe(
			llm.bindTools(tools)
		);

		const response = await chain.invoke({
			time: new Date().toISOString(),
			messages: state.messages,
		});

		return { messages: [response] };
	};

     

   /* ============================================================
	   4. TOOLS NODE
	============================================================ */
	//const toolsNode = new ToolNode(tools);
    const executeTools = async (state) => {

      const toolNode = new ToolNode(state.activeTools);

      return await toolNode.invoke(state);
  };
  

    /* ============================================================
	   5. GRAPH
	============================================================ */

  const workflow = new StateGraph(AgentState)
    .addNode("tool_loader", loadTools)
    .addNode("assistant", callAssistant)
    .addNode("tools", executeTools)
    .addEdge("__start__", "tool_loader")
    .addEdge("tool_loader", "assistant")
    .addConditionalEdges("assistant", toolsCondition)
    .addEdge("tools", "assistant");


   /* ============================================================
	   6. MEMORY / CHECKPOINTER
	============================================================ */
	const memory = new SessionStorageCheckpointer();

	return workflow.compile({
		checkpointer: memory,
	});


}

