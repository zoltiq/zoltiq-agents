export async function createAgent(deps) {
	const { 
      restUrl, nonce, z, Annotation, StateGraph, MessagesAnnotation, 
      tool, getExternalTools, ToolNode, toolsCondition, ToolMessage,
      ChatPromptTemplate, HumanMessage, AIMessage, uuidv4,
      SessionStorageCheckpointer, llmService, on, actions,
      setPath, updateDataModel, createSurface, deleteSurface, updateComponents,
      widgetStore, activeSurfaceId, agentState, basicFunctions
	} = deps;

  
  let exTools = await getExternalTools("document-search");
  const allTools = [...exTools];


   /* ============================================================
	   1. PROMPT
	============================================================ */
   const assistantPrompt = ChatPromptTemplate.fromMessages([
			["system",
`Jesteś asystentem serwisu elektronicznego. 
				

Language messages: PL   
Current time: {time}`
],
["placeholder", "{messages}"],
]);


   /* ============================================================
	   2. STATE
	============================================================ */
	const AgentState = Annotation.Root({
		...MessagesAnnotation.spec

	});


   /* ============================================================
	   3. NODES
	============================================================ */


   /* ---------- ASSISTANT NODE ---------- */
	const callAssistant = async (state, config) => {

        const llm = llmService.get("chatgpt-4o-mini");

		const chain = assistantPrompt.pipe(llm.bindTools(allTools));

		const response = await chain.invoke({
			time: new Date().toISOString(),
			messages: state.messages,
		});

		return { messages: [response] };
	};


  

   /* ============================================================
	   4. TOOLS NODE
	============================================================ */
	const toolsNode = new ToolNode(allTools);

    /* ============================================================
	   5. GRAPH
	============================================================ */
	const workflow = new StateGraph(AgentState)
      .addNode("assistant", callAssistant)
      .addNode("tools", toolsNode)
      .addEdge("__start__", "assistant")
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
