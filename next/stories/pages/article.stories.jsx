import { NodeArticle } from '../../components/node--article';

export default {
    title: "Pages/Article",
    component: NodeArticle,
};


export const ArticlePage = (args) => <NodeArticle node={args} />;

ArticlePage.args = {
    body: {
        processed: "Lorem ipsum"
    },
    created: new Date(),
    title: "Test Article",
    type: "node--article",
    uid: {
        display_name: "Testy MacTestface"
    }
};