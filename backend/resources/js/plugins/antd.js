import {
    Alert,
    Badge,
    Button,
    Card,
    Checkbox,
    Col,
    ConfigProvider,
    Descriptions,
    Divider,
    Empty,
    Form,
    Input,
    InputNumber,
    Layout,
    Menu,
    Modal,
    Popconfirm,
    Radio,
    Row,
    Segmented,
    Select,
    Space,
    Spin,
    Statistic,
    Table,
    Tag,
    Typography,
} from 'ant-design-vue';

const { TextArea } = Input;
import ruRU from 'ant-design-vue/es/locale/ru_RU';

const components = [
    Alert,
    Badge,
    Button,
    Card,
    Checkbox,
    Col,
    ConfigProvider,
    Descriptions,
    Divider,
    Empty,
    Form,
    Input,
    TextArea,
    InputNumber,
    Layout,
    Menu,
    Modal,
    Popconfirm,
    Radio,
    Row,
    Segmented,
    Select,
    Space,
    Spin,
    Statistic,
    Table,
    Tag,
    Typography,
];

export function registerAntDesign(app) {
    components.forEach((component) => app.use(component));
}

export const antLocale = ruRU;

export { default as antPaginationLocale } from 'ant-design-vue/es/vc-pagination/locale/ru_RU';
